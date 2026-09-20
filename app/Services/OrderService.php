<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderActivity;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
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

                // Verify available stock availability
                $availableStock = max(0, (int) $product->stock_quantity - (int) $product->reserved_quantity);
                if ($availableStock < $qty) {
                    throw new \Exception("Product '{$product->name}' only has {$availableStock} available units.");
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
                throw new \Exception("Minimum order value is AED {$mov}. Please add more items to your cart.");
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

            // Save order items & handle stock (POS = instant sale, Online = reservation)
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

                if ($source === 'POS') {
                    // Instant physical sale for POS counter checkout
                    $stockBefore = (int) $product->stock_quantity;
                    $stockAfter = max(0, $stockBefore - $qty);
                    $product->update(['stock_quantity' => $stockAfter]);

                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => StockMovement::TYPE_SALE,
                        'quantity' => -$qty,
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockAfter,
                        'reference_type' => 'Order',
                        'reference_id' => $order->id,
                        'reason' => "POS Sale #{$orderNumber}",
                        'created_by' => $options['user_name'] ?? 'POS Cashier',
                    ]);
                } else {
                    // Reserve stock for online/delivery order
                    $product->reserveStock($qty);

                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => StockMovement::TYPE_RESERVATION,
                        'quantity' => $qty,
                        'stock_before' => (int) $product->stock_quantity,
                        'stock_after' => (int) $product->stock_quantity,
                        'reference_type' => 'Order',
                        'reference_id' => $order->id,
                        'reason' => "Order #{$orderNumber} placed via {$source}",
                        'created_by' => $options['user_name'] ?? 'System/Customer',
                    ]);
                }
            }

            $this->logActivity($order, 'order_created', "Order #{$order->order_number} created via {$source}");

            return $order;
        });
    }

    /**
     * 1. Confirm Order (Awaiting WhatsApp / Pending -> Confirmed)
     */
    public function confirmOrder(Order $order, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($order, $actor) {
            $fromStatus = $order->status;
            $order->update([
                'status' => 'confirmed',
                'accepted_at' => now(),
            ]);

            $this->logStatusChange($order, $fromStatus, 'confirmed', 'Order accepted and confirmed', $actor);
            $this->logActivity($order, 'order_confirmed', "Order #{$order->order_number} confirmed by " . ($actor?->name ?? 'Admin'), null, $actor);

            return $order;
        });
    }

    /** Backward-compatible alias */
    public function acceptOrder(Order $order): Order
    {
        return $this->confirmOrder($order, auth()->user());
    }

    /**
     * 2. Start Preparing / Picking
     */
    public function startPreparing(Order $order, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($order, $actor) {
            $fromStatus = $order->status;
            $order->update([
                'status' => 'preparing',
                'preparing_at' => now(),
                'picking_status' => 'in_progress',
            ]);

            $this->logStatusChange($order, $fromStatus, 'preparing', 'Staff started picking & preparing', $actor);
            $this->logActivity($order, 'picking_started', "Grocery preparation started by " . ($actor?->name ?? 'Staff'), null, $actor);

            return $order;
        });
    }

    /** Backward-compatible alias */
    public function prepareOrder(Order $order): Order
    {
        return $this->startPreparing($order, auth()->user());
    }

    /**
     * 3. Pick Item (Barcode Scanning / Checkbox)
     */
    public function pickItem(Order $order, int $orderItemId, int $qtyPicked, ?User $actor = null): OrderItem
    {
        return DB::transaction(function () use ($order, $orderItemId, $qtyPicked, $actor) {
            $item = OrderItem::where('order_id', $order->id)->findOrFail($orderItemId);
            $item->update([
                'picked_quantity' => $qtyPicked,
                'item_status' => $qtyPicked >= $item->quantity ? 'picked' : ($qtyPicked > 0 ? 'partially_picked' : 'pending'),
            ]);

            // Check if all items in order are picked
            $remaining = OrderItem::where('order_id', $order->id)->whereColumn('picked_quantity', '<', 'quantity')->count();
            if ($remaining === 0) {
                $order->update(['picking_status' => 'completed']);
            }

            $this->logActivity($order, 'item_picked', "Picked {$qtyPicked}/{$item->quantity} × {$item->product_name}", [
                'item_id' => $item->id,
                'picked_quantity' => $qtyPicked,
                'required_quantity' => $item->quantity,
            ], $actor);

            return $item;
        });
    }

    /**
     * 4. Mark Ready (Preparing -> Ready)
     */
    public function markReady(Order $order, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($order, $actor) {
            $fromStatus = $order->status;
            $order->update([
                'status' => 'ready',
                'ready_at' => now(),
                'picking_status' => 'completed',
            ]);

            $this->logStatusChange($order, $fromStatus, 'ready', 'Order packed and ready for dispatch', $actor);
            $this->logActivity($order, 'order_ready', "Order #{$order->order_number} packed and ready for delivery", null, $actor);

            return $order;
        });
    }

    /**
     * 5. Assign Driver
     */
    public function assignDriver(Order $order, int $driverId, ?User $actor = null): Order
    {
        $driver = User::findOrFail($driverId);
        $order->update(['delivery_staff_id' => $driver->id]);

        $this->logActivity($order, 'driver_assigned', "Assigned to driver {$driver->name}", [
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
        ], $actor);

        return $order;
    }

    /**
     * 6. Out for Delivery / Dispatch
     */
    public function dispatchOrder(Order $order, ?int $deliveryStaffId = null, ?float $customDeliveryCost = null, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($order, $deliveryStaffId, $customDeliveryCost, $actor) {
            $fromStatus = $order->status;
            $driverId = $deliveryStaffId ?: $order->delivery_staff_id;
            $deliveryCost = $customDeliveryCost ?? $order->internal_delivery_cost;
            $netProfit = $order->gross_profit - $deliveryCost;

            $order->update([
                'status' => 'out_for_delivery',
                'delivery_staff_id' => $driverId,
                'internal_delivery_cost' => $deliveryCost,
                'net_profit' => $netProfit,
                'out_for_delivery_at' => now(),
            ]);

            $driverName = $order->deliveryStaff?->name ?? 'Driver';
            $this->logStatusChange($order, $fromStatus, 'out_for_delivery', "Dispatched with {$driverName}", $actor);
            $this->logActivity($order, 'out_for_delivery', "Dispatched with driver: {$driverName}", [
                'driver_id' => $driverId,
            ], $actor);

            return $order;
        });
    }

    /** Backward-compatible alias */
    public function outForDelivery(Order $order, ?int $deliveryStaffId = null, ?float $customDeliveryCost = null): Order
    {
        return $this->dispatchOrder($order, $deliveryStaffId, $customDeliveryCost, auth()->user());
    }

    /**
     * 7. Mark Delivered & Finalize Physical Sale
     */
    public function deliverOrder(Order $order, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($order, $actor) {
            $order = Order::lockForUpdate()->with('items')->findOrFail($order->id);
            if ($order->status === 'delivered') {
                return $order;
            }

            $fromStatus = $order->status;

            // Finalize physical sale: decrement physical stock and clear reservation
            if ($order->order_source !== 'POS') {
                foreach ($order->items as $item) {
                    if ($item->product_id) {
                        $product = Product::lockForUpdate()->find($item->product_id);
                        if ($product) {
                            $stockBefore = (int) $product->stock_quantity;
                            $product->finalizeSale($item->quantity);

                            StockMovement::create([
                                'product_id' => $product->id,
                                'type' => StockMovement::TYPE_SALE,
                                'quantity' => -$item->quantity,
                                'stock_before' => $stockBefore,
                                'stock_after' => (int) $product->stock_quantity,
                                'reference_type' => 'Order',
                                'reference_id' => $order->id,
                                'reason' => "Order delivered #{$order->order_number} to {$order->customer_villa}",
                                'created_by' => $actor?->name ?? 'Delivery Staff',
                            ]);
                        }
                    }
                }
            }

            $order->update([
                'status' => 'delivered',
                'delivered_at' => now(),
            ]);

            $this->logStatusChange($order, $fromStatus, 'delivered', 'Order delivered to customer villa', $actor);
            $this->logActivity($order, 'delivered', "Order delivered successfully to {$order->customer_villa}", null, $actor);

            return $order;
        });
    }

    /**
     * 8. Collect Cash on Delivery (COD)
     */
    public function collectCod(Order $order, float $amount, ?string $differenceReason = null, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($order, $amount, $differenceReason, $actor) {
            $expected = (float) $order->total_amount;
            $isExact = abs($amount - $expected) < 0.01;

            $order->update([
                'payment_status' => 'paid',
                'cod_collected_at' => now(),
                'cod_collected_amount' => $amount,
                'cod_collected_by' => $actor?->name ?? 'Admin',
                'cod_difference_reason' => $isExact ? null : $differenceReason,
            ]);

            $this->logActivity($order, 'payment_collected', "Cash collected: AED " . number_format($amount, 2) . ($isExact ? ' (Full Amount)' : " [Diff Reason: {$differenceReason}]"), [
                'amount' => $amount,
                'expected' => $expected,
                'difference' => $expected - $amount,
                'collected_by' => $actor?->name ?? 'Admin',
            ], $actor);

            return $order;
        });
    }

    /**
     * 9. Record Failed Delivery
     */
    public function failDelivery(Order $order, string $reason, ?string $notes = null, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($order, $reason, $notes, $actor) {
            $order = Order::lockForUpdate()->with('items')->findOrFail($order->id);
            if ($order->status === 'failed_delivery') {
                return $order;
            }

            $fromStatus = $order->status;

            // If order was already finalized as delivered, return stock; otherwise release reservation
            foreach ($order->items as $item) {
                if ($item->product_id) {
                    $product = Product::lockForUpdate()->find($item->product_id);
                    if ($product) {
                        if ($fromStatus === 'delivered') {
                            $stockBefore = (int) $product->stock_quantity;
                            $product->returnDeliveredStock($item->quantity);

                            StockMovement::create([
                                'product_id' => $product->id,
                                'type' => StockMovement::TYPE_CUSTOMER_RETURN,
                                'quantity' => $item->quantity,
                                'stock_before' => $stockBefore,
                                'stock_after' => (int) $product->stock_quantity,
                                'reference_type' => 'Order',
                                'reference_id' => $order->id,
                                'reason' => "Delivery failed return #{$order->order_number}: {$reason}",
                                'created_by' => $actor?->name ?? 'Delivery Staff',
                            ]);
                        } else {
                            $product->releaseReservation($item->quantity);

                            StockMovement::create([
                                'product_id' => $product->id,
                                'type' => StockMovement::TYPE_RESERVATION_RELEASE,
                                'quantity' => -$item->quantity,
                                'stock_before' => (int) $product->stock_quantity,
                                'stock_after' => (int) $product->stock_quantity,
                                'reference_type' => 'Order',
                                'reference_id' => $order->id,
                                'reason' => "Reservation released (Failed Delivery) #{$order->order_number}: {$reason}",
                                'created_by' => $actor?->name ?? 'Delivery Staff',
                            ]);
                        }
                    }
                }
            }

            $order->update([
                'status' => 'failed_delivery',
                'failed_delivery_at' => now(),
                'failed_delivery_reason' => $reason,
                'delivery_notes' => $notes ?: $order->delivery_notes,
            ]);

            $this->logStatusChange($order, $fromStatus, 'failed_delivery', "Delivery failed: {$reason}", $actor);
            $this->logActivity($order, 'delivery_failed', "Delivery attempt failed. Reason: {$reason}", [
                'reason' => $reason,
                'notes' => $notes,
            ], $actor);

            return $order;
        });
    }

    /**
     * 10. Cancel Order & Release Stock Atomically
     */
    public function cancelOrder(Order $order, string $reason = 'Cancelled by admin', ?User $actor = null): Order
    {
        return DB::transaction(function () use ($order, $reason, $actor) {
            $order = Order::lockForUpdate()->with('items')->findOrFail($order->id);
            if ($order->status === 'cancelled') {
                return $order;
            }

            $fromStatus = $order->status;

            // If order was already finalized as delivered, return physical stock; otherwise release reservation
            foreach ($order->items as $item) {
                if ($item->product_id) {
                    $product = Product::lockForUpdate()->find($item->product_id);
                    if ($product) {
                        if ($fromStatus === 'delivered') {
                            $stockBefore = (int) $product->stock_quantity;
                            $product->returnDeliveredStock($item->quantity);

                            StockMovement::create([
                                'product_id' => $product->id,
                                'type' => StockMovement::TYPE_CUSTOMER_RETURN,
                                'quantity' => $item->quantity,
                                'stock_before' => $stockBefore,
                                'stock_after' => (int) $product->stock_quantity,
                                'reference_type' => 'Order',
                                'reference_id' => $order->id,
                                'reason' => "Customer return from Cancelled Order #{$order->order_number}: {$reason}",
                                'created_by' => $actor?->name ?? 'Admin',
                            ]);
                        } else {
                            $product->releaseReservation($item->quantity);

                            StockMovement::create([
                                'product_id' => $product->id,
                                'type' => StockMovement::TYPE_RESERVATION_RELEASE,
                                'quantity' => -$item->quantity,
                                'stock_before' => (int) $product->stock_quantity,
                                'stock_after' => (int) $product->stock_quantity,
                                'reference_type' => 'Order',
                                'reference_id' => $order->id,
                                'reason' => "Reservation released for Cancelled Order #{$order->order_number}: {$reason}",
                                'created_by' => $actor?->name ?? 'Admin',
                            ]);
                        }
                    }
                }
            }

            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            $this->logStatusChange($order, $fromStatus, 'cancelled', $reason, $actor);
            $this->logActivity($order, 'cancelled', "Order cancelled. Reason: {$reason}", [
                'reason' => $reason,
            ], $actor);

            return $order;
        });
    }

    /**
     * 11. Expire Awaiting WhatsApp Order & Release Stock
     */
    public function expireOrder(Order $order, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($order, $actor) {
            $order = Order::lockForUpdate()->with('items')->findOrFail($order->id);
            if (!in_array($order->status, ['awaiting_whatsapp', 'pending'])) {
                return $order;
            }

            $fromStatus = $order->status;

            // Release reserved stock back into available pool
            foreach ($order->items as $item) {
                if ($item->product_id) {
                    $product = Product::lockForUpdate()->find($item->product_id);
                    if ($product) {
                        $product->releaseReservation($item->quantity);

                        StockMovement::create([
                            'product_id' => $product->id,
                            'type' => StockMovement::TYPE_RESERVATION_RELEASE,
                            'quantity' => -$item->quantity,
                            'stock_before' => (int) $product->stock_quantity,
                            'stock_after' => (int) $product->stock_quantity,
                            'reference_type' => 'Order',
                            'reference_id' => $order->id,
                            'reason' => "Released reservation for Expired Order #{$order->order_number}",
                            'created_by' => 'System/Expiry',
                        ]);
                    }
                }
            }

            $order->update([
                'status' => 'expired',
                'cancel_reason' => 'Customer did not complete WhatsApp confirmation within allowed window',
            ]);

            $this->logStatusChange($order, $fromStatus, 'expired', 'WhatsApp confirmation timed out', $actor);
            $this->logActivity($order, 'expired', 'Order marked as expired. Reserved stock released back to store.', null, $actor);

            return $order;
        });
    }

    /**
     * Log Order Activity
     */
    public function logActivity(Order $order, string $activityType, string $description, ?array $metadata = null, ?User $actor = null): OrderActivity
    {
        $actorUser = $actor ?: auth()->user();

        return OrderActivity::create([
            'order_id' => $order->id,
            'actor_type' => $actorUser ? $actorUser->role : 'system',
            'actor_id' => $actorUser?->id,
            'actor_name' => $actorUser?->name ?? 'System',
            'activity_type' => $activityType,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log Status Transition in OrderStatusHistory
     */
    public function logStatusChange(Order $order, ?string $fromStatus, string $toStatus, ?string $reason = null, ?User $actor = null): OrderStatusHistory
    {
        $actorUser = $actor ?: auth()->user();

        return OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $actorUser?->name ?? 'System',
            'reason' => $reason,
        ]);
    }
}

