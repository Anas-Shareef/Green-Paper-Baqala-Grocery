<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Api\v1\BaseApiController;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\OrderService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminOrderController extends BaseApiController
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['items', 'customer', 'deliveryStaff']);

        if ($request->has('status') && !empty($request->input('status'))) {
            $status = $request->input('status');
            if ($status === 'active') {
                $query->whereIn('status', ['awaiting_whatsapp', 'pending', 'confirmed', 'accepted', 'preparing', 'ready', 'out_for_delivery']);
            } elseif ($status === 'exceptions') {
                $query->whereIn('status', ['cancelled', 'expired', 'failed_delivery', 'returned', 'refunded']);
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->has('payment_status') && !empty($request->input('payment_status'))) {
            $query->where('payment_status', $request->input('payment_status'));
        }

        if ($request->has('order_source') && !empty($request->input('order_source'))) {
            $query->where('order_source', $request->input('order_source'));
        }

        if ($request->has('delivery_staff_id') && !empty($request->input('delivery_staff_id'))) {
            $query->where('delivery_staff_id', $request->input('delivery_staff_id'));
        }

        if ($request->has('q') && !empty(trim($request->input('q')))) {
            $q = trim($request->input('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('order_number', 'like', "%{$q}%")
                    ->orWhere('customer_villa', 'like', "%{$q}%")
                    ->orWhere('customer_name_snapshot', 'like', "%{$q}%")
                    ->orWhere('customer_phone_snapshot', 'like', "%{$q}%")
                    ->orWhereHas('customer', function ($cq) use ($q) {
                        $cq->where('name', 'like', "%{$q}%")
                           ->orWhere('phone', 'like', "%{$q}%");
                    });
            });
        }

        $perPage = (int) $request->input('per_page', 20);
        $orders = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->paginatedResponse($orders, 'Admin orders retrieved successfully');
    }

    public function show(string $id): JsonResponse
    {
        $order = Order::with(['items.product', 'customer', 'deliveryStaff', 'activities', 'statusHistory'])->find($id);
        if (!$order) {
            return $this->errorResponse('Order not found', [], 404);
        }

        return $this->successResponse($order, 'Order details retrieved successfully');
    }

    public function confirm(string $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $this->orderService->confirmOrder($order, auth()->user());
        return $this->successResponse($order->fresh(['items', 'customer', 'deliveryStaff']), 'Order confirmed');
    }

    public function prepare(string $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $this->orderService->startPreparing($order, auth()->user());
        return $this->successResponse($order->fresh(['items', 'customer', 'deliveryStaff']), 'Order marked as preparing');
    }

    public function ready(string $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $this->orderService->markReady($order, auth()->user());
        return $this->successResponse($order->fresh(['items', 'customer', 'deliveryStaff']), 'Order marked as ready');
    }

    public function assignDriver(Request $request, string $id): JsonResponse
    {
        $request->validate(['driver_id' => 'required|integer|exists:users,id']);
        $order = Order::findOrFail($id);
        $this->orderService->assignDriver($order, (int) $request->input('driver_id'), auth()->user());
        return $this->successResponse($order->fresh(['deliveryStaff']), 'Driver assigned');
    }

    public function dispatch(Request $request, string $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $driverId = $request->input('driver_id') ? (int) $request->input('driver_id') : null;
        $this->orderService->dispatchOrder($order, $driverId, null, auth()->user());
        return $this->successResponse($order->fresh(['deliveryStaff']), 'Order dispatched');
    }

    public function deliver(string $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $this->orderService->deliverOrder($order, auth()->user());
        return $this->successResponse($order->fresh(), 'Order marked as delivered');
    }

    public function payment(Request $request, string $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $amount = (float) $request->input('amount', $order->total_amount);
        $diffReason = $request->input('difference_reason');
        $this->orderService->collectCod($order, $amount, $diffReason, auth()->user());
        return $this->successResponse($order->fresh(), 'COD payment recorded');
    }

    public function failDelivery(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:255']);
        $order = Order::findOrFail($id);
        $this->orderService->failDelivery($order, $request->input('reason'), $request->input('notes'), auth()->user());
        return $this->successResponse($order->fresh(), 'Failed delivery recorded');
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:255']);
        $order = Order::findOrFail($id);
        $this->orderService->cancelOrder($order, $request->input('reason'), auth()->user());
        return $this->successResponse($order->fresh(), 'Order cancelled and stock returned');
    }

    public function pickItem(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'order_item_id' => 'required|integer|exists:order_items,id',
            'quantity' => 'required|integer|min:0',
        ]);
        $order = Order::findOrFail($id);
        $item = $this->orderService->pickItem($order, (int) $request->input('order_item_id'), (int) $request->input('quantity'), auth()->user());
        return $this->successResponse($item, 'Item picking updated');
    }

    public function activity(string $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $activities = $order->activities()->get();
        return $this->successResponse($activities, 'Order activities retrieved');
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,confirmed,accepted,preparing,ready,out_for_delivery,delivered,cancelled,expired,failed_delivery',
            'payment_status' => 'nullable|in:pending,paid,refunded',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $order = Order::with('items')->find($id);
        if (!$order) {
            return $this->errorResponse('Order not found', [], 404);
        }

        $newStatus = $request->input('status');
        $actor = auth()->user();

        switch ($newStatus) {
            case 'confirmed':
            case 'accepted':
                $this->orderService->confirmOrder($order, $actor);
                break;
            case 'preparing':
                $this->orderService->startPreparing($order, $actor);
                break;
            case 'ready':
                $this->orderService->markReady($order, $actor);
                break;
            case 'out_for_delivery':
                $this->orderService->dispatchOrder($order, null, null, $actor);
                break;
            case 'delivered':
                $this->orderService->deliverOrder($order, $actor);
                break;
            case 'cancelled':
                $this->orderService->cancelOrder($order, $request->input('reason', 'Cancelled from Admin API'), $actor);
                break;
            case 'expired':
                $this->orderService->expireOrder($order, $actor);
                break;
            case 'failed_delivery':
                $this->orderService->failDelivery($order, $request->input('reason', 'Customer unreachable'), null, $actor);
                break;
        }

        if ($request->has('payment_status')) {
            $order->update(['payment_status' => $request->input('payment_status')]);
        }

        return $this->successResponse($order->fresh(['items', 'customer', 'deliveryStaff']), 'Order status updated successfully');
    }

    public function bulkAction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_ids' => 'required|array|min:1',
            'action' => 'required|in:confirm,prepare,ready,dispatch,cancel',
            'driver_id' => 'nullable|integer|exists:users,id',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $action = $request->input('action');
        $orderIds = $request->input('order_ids');
        $driverId = $request->input('driver_id');
        $reason = $request->input('reason', 'Bulk action performed');
        $actor = auth()->user();

        $processed = 0;
        foreach ($orderIds as $id) {
            $order = Order::find($id);
            if (!$order) continue;

            if ($action === 'confirm') $this->orderService->confirmOrder($order, $actor);
            elseif ($action === 'prepare') $this->orderService->startPreparing($order, $actor);
            elseif ($action === 'ready') $this->orderService->markReady($order, $actor);
            elseif ($action === 'dispatch') $this->orderService->dispatchOrder($order, $driverId, null, $actor);
            elseif ($action === 'cancel') $this->orderService->cancelOrder($order, $reason, $actor);

            $processed++;
        }

        return $this->successResponse(['processed' => $processed], "Successfully processed {$processed} orders");
    }

    public function export(Request $request)
    {
        $query = Order::with(['customer', 'deliveryStaff'])->orderBy('id', 'desc');

        if ($request->has('status') && !empty($request->input('status'))) {
            $query->where('status', $request->input('status'));
        }

        $orders = $query->get();
        $csvHeader = ['Order No', 'Customer Order', 'Customer', 'Phone', 'Villa', 'Address', 'Subtotal (AED)', 'Total (AED)', 'Payment', 'Payment Status', 'Status', 'Driver', 'Source', 'Created At'];

        $callback = function () use ($orders, $csvHeader) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $csvHeader);

            foreach ($orders as $o) {
                fputcsv($file, [
                    $o->order_number,
                    $o->customer_order_number,
                    $o->customer_name_snapshot ?: ($o->customer->name ?? 'Walk-in'),
                    $o->customer_phone_snapshot ?: ($o->customer->phone ?? ''),
                    $o->customer_villa ?? '',
                    $o->customer_address ?? '',
                    $o->subtotal,
                    $o->total_amount,
                    $o->payment_method,
                    $o->payment_status,
                    $o->status,
                    $o->deliveryStaff?->name ?? 'Unassigned',
                    $o->order_source,
                    $o->created_at->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="baqqala_orders_' . date('Ymd_His') . '.csv"',
        ]);
    }
}

