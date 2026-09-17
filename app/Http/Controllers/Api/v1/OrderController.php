<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrderController extends BaseApiController
{
    /**
     * Store New Customer Order (Atomic Database Transaction)
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'delivery_address' => 'required|string',
            'payment_method' => 'required|string|in:cash,card,online,cod',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Order validation failed', $validator->errors(), 422);
        }

        try {
            $order = DB::transaction(function () use ($request) {
                $phone = trim($request->input('customer_phone'));
                $name = trim($request->input('customer_name'));

                // Find or create customer
                $customer = Customer::firstOrCreate(
                    ['phone' => $phone],
                    ['name' => $name, 'address' => $request->input('delivery_address'), 'total_orders' => 0]
                );
                $customer->increment('total_orders');

                $orderNumber = 'ORD-' . strtoupper(Str::random(6)) . '-' . rand(100, 999);
                $subtotal = 0;

                // Create Order record
                $order = Order::create([
                    'order_number' => $orderNumber,
                    'customer_id' => $customer->id,
                    'customer_name' => $name,
                    'customer_phone' => $phone,
                    'delivery_address' => $request->input('delivery_address'),
                    'payment_method' => $request->input('payment_method'),
                    'status' => 'pending',
                    'payment_status' => 'pending',
                    'notes' => $request->input('notes'),
                    'subtotal' => 0,
                    'delivery_fee' => 5.00,
                    'total' => 0,
                ]);

                foreach ($request->input('items') as $itemData) {
                    // Fetch authoritative price from DB — Never trust client-side prices!
                    $product = Product::lockForUpdate()->findOrFail($itemData['product_id']);

                    if ($product->status !== 'active') {
                        throw new \Exception("Product '{$product->name}' is currently unavailable.");
                    }

                    if ($product->stock_quantity < $itemData['quantity']) {
                        throw new \Exception("Insufficient stock for '{$product->name}'. Available: {$product->stock_quantity}");
                    }

                    $unitPrice = (float) ($product->sale_price ?: $product->price);
                    $lineTotal = $unitPrice * $itemData['quantity'];
                    $subtotal += $lineTotal;

                    // Create Order Item
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'unit_price' => $unitPrice,
                        'wholesale_cost' => $product->cost_price ?? 0,
                        'quantity' => $itemData['quantity'],
                        'total' => $lineTotal,
                    ]);

                    // Update Inventory & Record Stock Movement
                    $prevQuantity = $product->stock_quantity;
                    $newQuantity = max(0, $prevQuantity - $itemData['quantity']);

                    $product->update(['stock_quantity' => $newQuantity]);

                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'sale',
                        'quantity' => -$itemData['quantity'],
                        'previous_quantity' => $prevQuantity,
                        'new_quantity' => $newQuantity,
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'reason' => "Customer Sale Order #{$order->order_number}",
                    ]);
                }

                $deliveryFee = 5.00;
                $grandTotal = $subtotal + $deliveryFee;

                $order->update([
                    'subtotal' => $subtotal,
                    'delivery_fee' => $deliveryFee,
                    'total' => $grandTotal,
                ]);

                return $order->load('items');
            });

            return $this->successResponse($order, 'Order created successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), [], 400);
        }
    }

    /**
     * Get Order Details by Order Number
     */
    public function show(string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)
            ->with(['items.product'])
            ->first();

        if (!$order) {
            return $this->errorResponse('Order not found', [], 404);
        }

        return $this->successResponse($order, 'Order details retrieved successfully');
    }

    /**
     * Get Customer Order History by Mobile Phone
     */
    public function customerHistory(Request $request): JsonResponse
    {
        $phone = trim($request->input('phone', ''));
        if (empty($phone)) {
            return $this->errorResponse('Phone number parameter is required', [], 422);
        }

        $orders = Order::where('customer_phone', $phone)
            ->with('items')
            ->orderBy('id', 'desc')
            ->get();

        return $this->successResponse($orders, 'Customer orders retrieved successfully');
    }
}
