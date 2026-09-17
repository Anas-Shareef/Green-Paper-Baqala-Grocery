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

            // 4. Address Processing
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
                $villa = trim($data['villa_number'] ?? $data['address']['villa_number'] ?? $customer->villa_number ?? 'Villa');
                $street = trim($data['delivery_address'] ?? $data['address']['street_address'] ?? $customer->address ?? ($customer->zone ? "Zone {$customer->zone}" : 'Villa Delivery'));
                $zone = trim($data['zone'] ?? $data['address']['zone'] ?? $customer->zone ?? '');
                $notes = trim($data['notes'] ?? $data['address']['delivery_notes'] ?? '');

                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('customer_addresses')) {
                        $isFirstAddress = CustomerAddress::where('customer_id', $customer->id)->count() === 0;

                        $address = CustomerAddress::create([
                            'customer_id' => $customer->id,
                            'label' => $data['address_label'] ?? 'Home',
                            'villa_number' => $villa,
                            'street_address' => $street,
                            'zone' => $zone,
                            'delivery_notes' => $notes,
                            'is_default' => $isFirstAddress || !empty($data['is_default']),
                        ]);

                        if ($isFirstAddress || !empty($data['is_default'])) {
                            CustomerAddress::where('customer_id', $customer->id)->where('id', '!=', $address->id)->update(['is_default' => false]);
                        }
                    }
                } catch (\Throwable $e) {}

                if (!$address) {
                    $address = (object) [
                        'villa_number' => $villa,
                        'street_address' => $street,
                        'zone' => $zone,
                        'delivery_notes' => $notes,
                    ];
                }
            }

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

                if ($product->stock_quantity < $itemData['quantity']) {
                    throw new \Exception("Insufficient stock for '{$product->name}'. Available: {$product->stock_quantity}");
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
            } catch (\Throwable $e) {}

            // 7. Create Order Items & Deduct Stock
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

                $prevQty = $i['product']->stock_quantity;
                $newQty = max(0, $prevQty - $i['quantity']);
                $i['product']->update(['stock_quantity' => $newQty]);

                StockMovement::create([
                    'product_id' => $i['product']->id,
                    'type' => 'sale',
                    'quantity' => -$i['quantity'],
                    'stock_before' => $prevQty,
                    'stock_after' => $newQty,
                    'previous_quantity' => $prevQty,
                    'new_quantity' => $newQty,
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'reason' => "Customer Sale Order #{$order->order_number}",
                ]);
            }

            // 8. Generate WhatsApp URL & Record Entry
            $wa = $this->whatsappService->generateClickToChat($order);

            return [
                'order' => $order->load(['items.product', 'customer']),
                'whatsapp_url' => $wa['whatsapp_url'],
                'message_body' => $wa['message_body'],
                'is_duplicate' => false,
            ];
        });
    }
}
