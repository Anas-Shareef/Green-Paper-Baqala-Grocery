<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    protected OrderService $orderService;
    protected WhatsAppService $whatsAppService;

    public function __construct(OrderService $orderService, WhatsAppService $whatsAppService)
    {
        $this->orderService = $orderService;
        $this->whatsAppService = $whatsAppService;
    }

    /**
     * Submit a new customer order from React PWA storefront.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'name' => 'required|string|max:255',
            'villa_number' => 'required|string|max:100',
            'address' => 'nullable|string',
            'payment_method' => 'nullable|string|in:Cash,Card,UPI',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $phone = preg_replace('/[^0-9]/', '', $request->phone);

        // Find or create customer
        $customer = Customer::firstOrCreate(
            ['phone' => $phone],
            [
                'name' => $request->name,
                'whatsapp_number' => $phone,
                'villa_number' => $request->villa_number,
                'address' => $request->address ?? "Villa {$request->villa_number}",
                'status' => 'active',
            ]
        );

        // Update customer villa/address if changed
        $customer->update([
            'name' => $request->name,
            'villa_number' => $request->villa_number,
            'address' => $request->address ?? $customer->address,
        ]);

        try {
            $order = $this->orderService->createOrder($request->items, [
                'customer_id' => $customer->id,
                'customer_villa' => $request->villa_number,
                'customer_address' => $request->address ?? $customer->address,
                'payment_method' => $request->payment_method ?? 'Cash',
                'order_source' => 'PWA',
                'notes' => $request->notes,
                'user_name' => "Customer {$customer->name}",
            ]);

            // Dispatch WhatsApp notification
            $this->whatsAppService->sendOrderConfirmation($order);

            $order->load(['items', 'customer']);
            $order->makeHidden(['wholesale_cost', 'product_cost', 'gross_profit', 'net_profit', 'internal_delivery_cost']);
            $order->items->makeHidden(['wholesale_cost']);

            return response()->json([
                'success' => true,
                'message' => "Order #{$order->order_number} submitted successfully!",
                'order' => $order,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Show tracking information for a specific order.
     */
    public function show(string $orderNumber): JsonResponse
    {
        $order = Order::with(['items', 'customer', 'deliveryStaff'])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $order->makeHidden(['wholesale_cost', 'product_cost', 'gross_profit', 'net_profit', 'internal_delivery_cost']);
        $order->items->makeHidden(['wholesale_cost']);

        return response()->json([
            'order' => $order,
            'timeline' => [
                ['status' => 'pending', 'label' => 'Order Placed', 'time' => $order->created_at, 'active' => true],
                ['status' => 'accepted', 'label' => 'Accepted', 'time' => $order->accepted_at, 'active' => in_array($order->status, ['accepted', 'preparing', 'out_for_delivery', 'delivered'])],
                ['status' => 'preparing', 'label' => 'Preparing', 'time' => $order->preparing_at, 'active' => in_array($order->status, ['preparing', 'out_for_delivery', 'delivered'])],
                ['status' => 'out_for_delivery', 'label' => 'Out for Delivery', 'time' => $order->out_for_delivery_at, 'active' => in_array($order->status, ['out_for_delivery', 'delivered'])],
                ['status' => 'delivered', 'label' => 'Delivered', 'time' => $order->delivered_at, 'active' => $order->status === 'delivered'],
            ]
        ]);
    }

    /**
     * Get order history for a customer phone number.
     */
    public function customerHistory(Request $request): JsonResponse
    {
        $phone = preg_replace('/[^0-9]/', '', $request->input('phone', ''));

        if (empty($phone)) {
            return response()->json([]);
        }

        $customer = Customer::where('phone', $phone)->first();
        if (!$customer) {
            return response()->json([]);
        }

        $orders = Order::with('items')
            ->where('customer_id', $customer->id)
            ->orderBy('id', 'desc')
            ->get();

        foreach ($orders as $o) {
            $o->makeHidden(['wholesale_cost', 'product_cost', 'gross_profit', 'net_profit', 'internal_delivery_cost']);
            $o->items->makeHidden(['wholesale_cost']);
        }

        return response()->json($orders);
    }
}
