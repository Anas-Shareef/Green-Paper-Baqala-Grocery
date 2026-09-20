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
        // Support header or body Idempotency-Key
        $idempotencyKey = $request->header('Idempotency-Key') ?: $request->input('idempotency_key');

        $validator = Validator::make($request->all(), [
            'customer_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:25',
            'phone' => 'nullable|string|max:25',
            'villa_number' => 'nullable|string|max:50',
            'street_address' => 'nullable|string|max:255',
            'delivery_address' => 'nullable|string|max:255',
            'address' => 'nullable',
            'zone' => 'nullable|string|max:100',
            'landmark' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'idempotency_key' => 'nullable|string|max:100',
            'order_source' => 'nullable|string|max:50',
            'address_id' => 'nullable|integer',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Order validation failed', $validator->errors(), 422);
        }

        // Verify that a customer mobile phone number is provided
        $rawPhone = $request->input('customer_phone') 
            ?? $request->input('phone') 
            ?? $request->input('customer.phone');

        if (empty(trim((string)$rawPhone))) {
            return $this->errorResponse('A valid mobile phone number is required.', [
                'customer_phone' => ['The customer phone number is required.']
            ], 422);
        }

        try {
            $input = $request->all();
            if ($idempotencyKey) {
                $input['idempotency_key'] = $idempotencyKey;
            }

            // Normalize customer identifiers
            $input['customer_phone'] = trim((string)$rawPhone);
            $input['customer_name'] = trim($input['customer_name'] ?? $input['name'] ?? $input['customer']['name'] ?? 'Valued Customer');

            // Normalize structured delivery address fields
            $input['villa_number'] = trim($input['villa_number'] ?? $input['address']['villa_number'] ?? '');
            $input['street_address'] = trim(
                $input['street_address'] 
                ?? $input['delivery_address'] 
                ?? (is_string($input['address'] ?? null) ? $input['address'] : ($input['address']['street_address'] ?? ''))
            );
            $input['delivery_address'] = $input['street_address'];
            $input['zone'] = trim($input['zone'] ?? $input['address']['zone'] ?? '');

            // Enforce Cash on Delivery (COD) strictly for customer checkout (PRD Section 13)
            $rawPm = strtolower(trim((string)($input['payment_method'] ?? 'cod')));
            if (!in_array($rawPm, ['cod', 'cash', 'cash_on_delivery', ''])) {
                return $this->errorResponse('Only Cash on Delivery (COD) is supported.', ['payment_method' => ['Only Cash on Delivery is supported']], 400);
            }
            $input['payment_method'] = 'cod';

            $result = $this->orderCreationService->createOrder($input);
            $order = $result['order'];

            return $this->successResponse([
                'order' => $order,
                'order_number' => $order->order_number,
                'customer_order_number' => $order->customer_order_number,
                'status' => $order->status,
                'payment_method' => $order->payment_method,
                'whatsapp_status' => $order->whatsapp_status,
                'whatsapp_url' => $result['whatsapp_url'],
                'message_body' => $result['message_body'],
                'whatsapp' => [
                    'url' => $result['whatsapp_url'],
                    'message' => $result['message_body'],
                ],
                'total_amount' => (string) $order->total_amount,
                'is_duplicate' => $result['is_duplicate'] ?? false,
            ], 'Order created successfully', 201);

        } catch (\InvalidArgumentException $e) {
            \Illuminate\Support\Facades\Log::warning('Order validation/business exception: ' . $e->getMessage(), [
                'phone_hash' => substr(hash('sha256', (string)$rawPhone), 0, 10),
            ]);
            return $this->errorResponse($e->getMessage(), ['order' => [$e->getMessage()]], 422);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Order creation error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
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
