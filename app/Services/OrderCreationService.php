<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderCreationService
{
    protected WhatsAppOrderService $whatsappService;

    public function __construct(WhatsAppOrderService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * Create Order Atomically in DB Transaction
     */
    public function createOrder(array $data): array
    {
        // 1. Idempotency Check
        if (!empty($data['idempotency_key'])) {
            $existing = Order::where('idempotency_key', $data['idempotency_key'])->with('items')->first();
            if ($existing) {
                $wa = $this->whatsappService->generateClickToChat($existing);
                return [
                    'order' => $existing,
                    'whatsapp_url' => $wa['whatsapp_url'],
                    'message_body' => $wa['message_body'],
                    'whatsapp' => [
                        'url' => $wa['whatsapp_url'],
                        'message' => $wa['message_body'],
                    ],
                    'is_duplicate' => true,
                ];
            }
        }

        return DB::transaction(function () use ($data) {
            // 2. Phone Normalization
            $rawPhone = $data['customer_phone'] ?? $data['customer']['phone'] ?? '';
            $phone = PhoneNumberService::normalize($rawPhone);
            $name = trim($data['customer_name'] ?? $data['customer']['name'] ?? 'Valued Customer');

            if (empty($phone)) {
                throw new \InvalidArgumentException('A valid mobile phone number is required.');
            }

            // 3. Customer Lookup or Creation
            $customer = PhoneNumberService::findCustomer($rawPhone);
            if (!$customer) {
                $customer = Customer::create([
                    'phone' => $phone,
                    'name' => $name,
                    'status' => 'active',
                ]);
            }

            // Update customer name if provided
            if ($name !== 'Valued Customer' && $customer->name === 'Valued Customer') {
                $customer->update(['name' => $name]);
            }

            // 4. Address Processing (Reuse or Create without duplication)
            $villa = trim($data['villa_number'] ?? $data['address']['villa_number'] ?? $customer->villa_number ?? '');
            $street = trim($data['street_address'] ?? $data['delivery_address'] ?? $data['address']['street_address'] ?? $customer->address ?? '');
            $zone = trim($data['zone'] ?? $data['address']['zone'] ?? $customer->zone ?? '');
            $notes = trim($data['notes'] ?? $data['address']['delivery_notes'] ?? '');

            $address = null;
            $rawAddressId = $data['address_id'] ?? null;

            if (!empty($rawAddressId) && is_numeric($rawAddressId)) {
                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('customer_addresses')) {
                        $address = CustomerAddress::where('customer_id', $customer->id)->find($rawAddressId);
                    }
                } catch (\Throwable $e) {}
            }

            if (!$address) {
                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('customer_addresses')) {
                        // Find matching existing address by villa or default
                        $existingQuery = CustomerAddress::where('customer_id', $customer->id);
                        if (!empty($villa)) {
                            $cleanVillaDigits = preg_replace('/[^\d]/', '', $villa);
                            $existingQuery->where(function ($q) use ($villa, $cleanVillaDigits) {
                                $q->where('villa_number', $villa);
                                if (!empty($cleanVillaDigits)) {
                                    $q->orWhere('villa_number', $cleanVillaDigits)
                                      ->orWhere('villa_number', "Villa {$cleanVillaDigits}");
                                }
                            });
                        }
                        $address = $existingQuery->first() 
                            ?? CustomerAddress::where('customer_id', $customer->id)->whereRaw('is_default = true')->first()
                            ?? CustomerAddress::where('customer_id', $customer->id)->first();

                        if ($address) {
                            // Update existing address record
                            $address->update([
                                'villa_number' => $villa ?: $address->villa_number,
                                'street_address' => $street ?: $address->street_address,
                                'zone' => $zone ?: $address->zone,
                                'delivery_notes' => $notes ?: $address->delivery_notes,
                            ]);
                        } else {
                            // Create first address for customer
                            $address = CustomerAddress::create([
                                'customer_id' => $customer->id,
                                'label' => $data['address_label'] ?? 'Home',
                                'villa_number' => $villa ?: 'Villa',
                                'street_address' => $street ?: 'Villa Delivery',
                                'zone' => $zone,
                                'delivery_notes' => $notes,
                                'is_default' => \Illuminate\Support\Facades\DB::raw('true'),
                            ]);
                        }
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Address processing in order tx warning: ' . $e->getMessage());
                }

                if (!$address) {
                    $address = (object) [
                        'villa_number' => $villa ?: 'Villa',
                        'street_address' => $street ?: 'Villa Delivery',
                        'zone' => $zone,
                        'delivery_notes' => $notes,
                    ];
                }
            }

            // Keep Customer profile fields synchronized
            try {
                $customerUpdates = [];
                if (!empty($villa) && $customer->villa_number !== $villa) {
                    $customerUpdates['villa_number'] = $villa;
                }
                if (!empty($street) && $customer->address !== $street) {
                    $customerUpdates['address'] = $street;
                }
                if (!empty($zone) && $customer->zone !== $zone) {
                    $customerUpdates['zone'] = $zone;
                }
                if (!empty($customerUpdates)) {
                    $customer->update($customerUpdates);
                }
            } catch (\Throwable $e) {}

            // 5. Customer Sequential Order Number (PRD Priority 1)
            $customerOrderCount = Order::where('customer_id', $customer->id)->count();
            $customerOrderNumber = $customerOrderCount + 1;

            // Sequential / Unique Global Order Number
            $nextId = (Order::max('id') ?? 0) + 1;
            $orderNumber = 'ORD-' . str_pad((string)$nextId, 6, '0', STR_PAD_LEFT);

            // Canonical address formatting (PRD Section 8)
            $canonicalAddress = PhoneNumberService::formatCanonicalAddress(
                $address->villa_number ?? null,
                $address->street_address ?? null,
                $address->zone ?? null
            );

            // 6. Order Calculation & Items
            $subtotal = 0;
            $itemsToCreate = [];

            foreach ($data['items'] as $itemData) {
                $product = Product::lockForUpdate()->findOrFail($itemData['product_id']);

                if ($product->status !== 'active') {
                    throw new \Exception("Product '{$product->name}' is currently unavailable.");
                }

                $availableStock = max(0, (int) $product->stock_quantity - (int) $product->reserved_quantity);
                if ($availableStock < $itemData['quantity']) {
                    throw new \Exception("Insufficient available stock for '{$product->name}'. Available: {$availableStock}, Requested: {$itemData['quantity']}");
                }

                $unitPrice = (float) ($product->sale_price ?: $product->retail_price ?: $product->price);
                $lineTotal = $unitPrice * $itemData['quantity'];
                $subtotal += $lineTotal;

                $itemsToCreate[] = [
                    'product' => $product,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $unitPrice,
                    'wholesale_cost' => $product->cost_price ?? 0,
                    'line_total' => $lineTotal,
                ];
            }

            // Minimum Order Value (MOV) & Delivery Charge Config
            $movThreshold = (float) config('app.minimum_order_value', 300);
            $deliveryFee = $subtotal >= $movThreshold ? 0.00 : 0.00; // Free villa delivery
            $grandTotal = $subtotal + $deliveryFee;

            // Enforce Cash on Delivery (PRD Section 13)
            $requestedPm = strtolower(trim($data['payment_method'] ?? 'cod'));
            if (!in_array($requestedPm, ['cod', 'cash', 'cash_on_delivery'])) {
                throw new \InvalidArgumentException('Only Cash on Delivery (COD) is supported.');
            }

            // 7. Create Order Record with Address Snapshot
            $order = Order::create([
                'order_number' => $orderNumber,
                'customer_order_number' => $customerOrderNumber,
                'customer_id' => $customer->id,
                'customer_name_snapshot' => $customer->name,
                'customer_phone_snapshot' => $customer->phone,
                'customer_villa' => $address->villa_number ?? null,
                'customer_address' => $canonicalAddress,
                'customer_notes_snapshot' => $data['notes'] ?? $address->delivery_notes ?? null,
                'subtotal' => $subtotal,
                'delivery_charge' => $deliveryFee,
                'total_amount' => $grandTotal,
                'payment_method' => 'cod',
                'payment_status' => 'pending',
                'status' => 'pending',
                'whatsapp_status' => 'prepared',
                'idempotency_key' => $data['idempotency_key'] ?? (string) Str::uuid(),
                'order_source' => $data['order_source'] ?? 'PWA',
                'notes' => $data['notes'] ?? null,
            ]);

            // Create AdminNotification Record for Realtime Admin Listening
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('admin_notifications')) {
                    \App\Models\AdminNotification::create([
                        'type' => 'new_order',
                        'title' => 'New Order Received',
                        'message' => "Order {$order->order_number} (Customer Order #{$customerOrderNumber}) from {$customer->name}",
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'is_read' => false,
                    ]);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('AdminNotification in order tx warning: ' . $e->getMessage());
            }

            // 7. Create Order Items & Reserve Stock
            foreach ($itemsToCreate as $i) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $i['product']->id,
                    'product_name' => $i['product']->name,
                    'unit_price' => $i['unit_price'],
                    'wholesale_cost' => $i['wholesale_cost'],
                    'quantity' => $i['quantity'],
                    'total' => $i['line_total'],
                ]);

                // Atomically increment reserved_quantity; physical stock_quantity on shelf remains untouched
                $i['product']->reserveStock($i['quantity']);

                StockMovement::create([
                    'product_id' => $i['product']->id,
                    'type' => StockMovement::TYPE_RESERVATION,
                    'quantity' => $i['quantity'],
                    'stock_before' => (int) $i['product']->stock_quantity,
                    'stock_after' => (int) $i['product']->stock_quantity,
                    'reference_type' => 'Order',
                    'reference_id' => $order->id,
                    'reason' => "Customer Order Stock Reservation #{$order->order_number}",
                    'created_by' => 'Online Customer',
                ]);
            }

            // 8. Generate WhatsApp URL & Record Entry
            $wa = $this->whatsappService->generateClickToChat($order);

            return [
                'order' => $order->load(['items.product', 'customer']),
                'whatsapp_url' => $wa['whatsapp_url'],
                'message_body' => $wa['message_body'],
                'whatsapp' => [
                    'url' => $wa['whatsapp_url'],
                    'message' => $wa['message_body'],
                ],
                'is_duplicate' => false,
            ];
        });
    }
}
