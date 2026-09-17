<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\Order;
use App\Services\OrderCreationService;
use App\Services\PhoneNumberService;
use App\Services\WhatsAppOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderController extends BaseApiController
{
    protected OrderCreationService $orderCreationService;
    protected WhatsAppOrderService $whatsappService;

    public function __construct(
        OrderCreationService $orderCreationService,
        WhatsAppOrderService $whatsappService
    ) {
        $this->orderCreationService = $orderCreationService;
        $this->whatsappService = $whatsappService;
    }

    /**
     * Store New Customer Order (Atomic Database Transaction & WhatsApp Generation)
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'villa_number' => 'nullable|string',
            'address' => 'nullable',
            'delivery_address' => 'nullable|string',
            'payment_method' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
            'idempotency_key' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Order validation failed', $validator->errors(), 422);
        }

        try {
            $input = $request->all();
            
            // Normalize payload fields for backwards compatibility
            $input['customer_name'] = $input['name'] ?? $input['customer_name'] ?? $input['customer']['name'] ?? 'Valued Customer';
            $input['customer_phone'] = $input['phone'] ?? $input['customer_phone'] ?? $input['customer']['phone'] ?? '';
            $input['villa_number'] = $input['villa_number'] ?? $input['address']['villa_number'] ?? '';
            $input['delivery_address'] = is_string($input['address'] ?? null) ? $input['address'] : ($input['delivery_address'] ?? $input['address']['street_address'] ?? '');

            $result = $this->orderCreationService->createOrder($input);

            return $this->successResponse([
                'order' => $result['order'],
                'order_number' => $result['order']->order_number,
                'status' => $result['order']->status,
                'whatsapp_status' => $result['order']->whatsapp_status,
                'whatsapp_url' => $result['whatsapp_url'],
                'message_body' => $result['message_body'],
                'total_amount' => (string) $result['order']->total_amount,
                'is_duplicate' => $result['is_duplicate'] ?? false,
            ], 'Order created successfully', 201);

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
            ->with(['items.product', 'customer'])
            ->first();

        if (!$order) {
            return $this->errorResponse('Order not found', [], 404);
        }

        $wa = $this->whatsappService->generateClickToChat($order);

        $data = $order->toArray();
        $data['whatsapp_url'] = $wa['whatsapp_url'];
        $data['message_body'] = $wa['message_body'];

        // Timeline progress status builder
        $data['timeline'] = [
            ['label' => 'Order Created (Pending)', 'active' => true, 'time' => $order->created_at],
            ['label' => 'WhatsApp Confirmation Prepared', 'active' => $order->whatsapp_status === 'prepared' || $order->whatsapp_status === 'sent', 'time' => $order->created_at],
            ['label' => 'Order Accepted by Baqqala', 'active' => in_array($order->status, ['confirmed', 'accepted', 'preparing', 'out_for_delivery', 'delivered']), 'time' => $order->accepted_at],
            ['label' => 'Preparing Order Items', 'active' => in_array($order->status, ['preparing', 'out_for_delivery', 'delivered']), 'time' => $order->preparing_at],
            ['label' => 'Out for Villa Delivery', 'active' => in_array($order->status, ['out_for_delivery', 'delivered']), 'time' => $order->out_for_delivery_at],
            ['label' => 'Delivered to Villa', 'active' => $order->status === 'delivered', 'time' => $order->delivered_at],
        ];

        return $this->successResponse($data, 'Order details retrieved successfully');
    }

    /**
     * Get Customer Order History by Mobile Phone
     */
    public function customerHistory(Request $request): JsonResponse
    {
        $rawPhone = trim($request->input('phone', ''));
        if (empty($rawPhone)) {
            return $this->errorResponse('Phone number parameter is required', [], 422);
        }

        $phone = PhoneNumberService::normalize($rawPhone);

        $orders = Order::where(function ($q) use ($phone, $rawPhone) {
            $q->where('customer_phone_snapshot', $phone)
              ->orWhere('customer_phone_snapshot', $rawPhone)
              ->orWhereHas('customer', function ($cq) use ($phone, $rawPhone) {
                  $cq->where('phone', $phone)->orWhere('phone', $rawPhone);
              });
        })
        ->with('items')
        ->orderBy('id', 'desc')
        ->get();

        return $this->successResponse($orders, 'Customer orders retrieved successfully');
    }
}
