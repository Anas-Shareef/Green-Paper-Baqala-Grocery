<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    /**
     * Create an order transactionally from validated items.
     *
     * @param array $items Array of ['product_id' => int, 'quantity' => int]
     * @param array $options Customer info, payment method, source, internal delivery cost, etc.
     * @return Order
     */
    public function createOrder(array $items, array $options = []): Order
    {
        return DB::transaction(function () use ($items, $options) {
            $source = $options['order_source'] ?? 'PWA';
            $paymentMethod = $options['payment_method'] ?? 'Cash';
            $customerId = $options['customer_id'] ?? null;
            $customerVilla = $options['customer_villa'] ?? null;
            $customerAddress = $options['customer_address'] ?? null;
            $notes = $options['notes'] ?? null;
            $internalDeliveryCost = (float) ($options['internal_delivery_cost'] ?? (float) BusinessSetting::get('default_internal_delivery_cost', 25.00));

            // Generate unique order number e.g., ORD-000123
            $nextId = (Order::max('id') ?? 0) + 1;
            $orderNumber = 'ORD-' . str_pad((string)$nextId, 6, '0', STR_PAD_LEFT);

            $subtotal = 0.00;
            $totalWholesaleCost = 0.00;
            $preparedItems = [];

            foreach ($items as $itemData) {
                $product = Product::lockForUpdate()->findOrFail($itemData['product_id']);
                $qty = (int) $itemData['quantity'];

                if ($qty <= 0) {
                    continue;
                }

                // Verify stock availability
                if ($product->stock_quantity < $qty) {
                    throw new \Exception("Product '{$product->name}' only has {$product->stock_quantity} units available.");
                }

                $itemTotal = (float) ($product->retail_price * $qty);
                $itemWholesaleTotal = (float) ($product->wholesale_cost * $qty);

                $subtotal += $itemTotal;
                $totalWholesaleCost += $itemWholesaleTotal;

                $preparedItems[] = [
                    'product' => $product,
                    'quantity' => $qty,
                    'unit_price' => $product->retail_price,
                    'wholesale_cost' => $product->wholesale_cost,
                    'total' => $itemTotal,
                ];
            }

            if (empty($preparedItems)) {
                throw new \Exception("Cannot create an empty order.");
            }

            // Minimum Order Value (MOV) check for delivery orders
            $mov = (float) BusinessSetting::get('minimum_order_value', 300.00);
            $deliveryCharge = 0.00;

            if ($source === 'PWA' && $subtotal < $mov) {
                throw new \Exception("Minimum order value is ₹{$mov}. Please add more items to your cart.");
            }

            $discountAmount = (float) ($options['discount_amount'] ?? 0.00);
            $totalAmount = max(0, $subtotal + $deliveryCharge - $discountAmount);

            // Calculate profit metrics
            // Gross Profit = Total Revenue - Product Wholesale Cost
            $grossProfit = $totalAmount - $totalWholesaleCost;

            // Net Profit = Total Revenue - Product Wholesale Cost - Internal Delivery Cost
            $effectiveDeliveryCost = ($source === 'POS') ? 0.00 : $internalDeliveryCost;
            $netProfit = $grossProfit - $effectiveDeliveryCost;

            // Create Order record
            $order = Order::create([
                'order_number' => $orderNumber,
                'customer_id' => $customerId,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'delivery_charge' => $deliveryCharge,
                'total_amount' => $totalAmount,
                'internal_delivery_cost' => $effectiveDeliveryCost,
                'product_cost' => $totalWholesaleCost,
                'gross_profit' => $grossProfit,
                'net_profit' => $netProfit,
                'payment_method' => $paymentMethod,
                'payment_status' => ($source === 'POS') ? 'paid' : 'pending',
                'status' => ($source === 'POS') ? 'delivered' : 'pending',
                'order_source' => $source,
                'customer_villa' => $customerVilla,
                'customer_address' => $customerAddress,
                'notes' => $notes,
                'delivered_at' => ($source === 'POS') ? now() : null,
            ]);

            // Save order items & adjust stock
            foreach ($preparedItems as $prep) {
                /** @var Product $product */
                $product = $prep['product'];
                $qty = $prep['quantity'];

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $qty,
                    'unit_price' => $prep['unit_price'],
                    'wholesale_cost' => $prep['wholesale_cost'],
                    'total' => $prep['total'],
                ]);

                // Deduct stock and log stock movement
                $stockBefore = $product->stock_quantity;
                $stockAfter = $stockBefore - $qty;

                $product->update([
                    'stock_quantity' => $stockAfter,
                ]);

                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => ($source === 'POS') ? 'POS Sale' : 'Online Order',
                    'quantity' => -$qty,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'reference_type' => 'Order',
                    'reference_id' => $order->id,
                    'reason' => "Order #{$orderNumber} placed via {$source}",
                    'created_by' => $options['user_name'] ?? 'System/Customer',
                ]);
            }

            return $order;
        });
    }

    public function acceptOrder(Order $order): Order
    {
        $order->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        return $order;
    }

    public function prepareOrder(Order $order): Order
    {
        $order->update([
            'status' => 'preparing',
            'preparing_at' => now(),
        ]);

        return $order;
    }

    public function outForDelivery(Order $order, ?int $deliveryStaffId = null, ?float $customDeliveryCost = null): Order
    {
        $deliveryCost = $customDeliveryCost ?? $order->internal_delivery_cost;
        $netProfit = $order->gross_profit - $deliveryCost;

        $order->update([
            'status' => 'out_for_delivery',
            'delivery_staff_id' => $deliveryStaffId,
            'internal_delivery_cost' => $deliveryCost,
            'net_profit' => $netProfit,
            'out_for_delivery_at' => now(),
        ]);

        return $order;
    }

    public function deliverOrder(Order $order): Order
    {
        $order->update([
            'status' => 'delivered',
            'payment_status' => 'paid',
            'delivered_at' => now(),
        ]);

        return $order;
    }

    public function cancelOrder(Order $order, string $reason = 'Cancelled by user/admin'): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            if ($order->status === 'cancelled') {
                return $order;
            }

            // Restore stock if previously deducted
            foreach ($order->items as $item) {
                if ($item->product) {
                    $product = Product::lockForUpdate()->find($item->product_id);
                    if ($product) {
                        $stockBefore = $product->stock_quantity;
                        $stockAfter = $stockBefore + $item->quantity;

                        $product->update([
                            'stock_quantity' => $stockAfter,
                        ]);

                        StockMovement::create([
                            'product_id' => $product->id,
                            'type' => 'Return',
                            'quantity' => $item->quantity,
                            'stock_before' => $stockBefore,
                            'stock_after' => $stockAfter,
                            'reference_type' => 'Order',
                            'reference_id' => $order->id,
                            'reason' => "Order #{$order->order_number} cancelled: {$reason}",
                            'created_by' => auth()->user()?->name ?? 'System',
                        ]);
                    }
                }
            }

            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            return $order;
        });
    }
}
