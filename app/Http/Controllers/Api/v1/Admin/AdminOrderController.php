<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Api\v1\BaseApiController;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminOrderController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::with('items');

        if ($request->has('status') && !empty($request->input('status'))) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('payment_status') && !empty($request->input('payment_status'))) {
            $query->where('payment_status', $request->input('payment_status'));
        }

        if ($request->has('q') && !empty(trim($request->input('q')))) {
            $q = trim($request->input('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('order_number', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")
                    ->orWhere('customer_phone', 'like', "%{$q}%");
            });
        }

        $orders = $query->orderBy('id', 'desc')->paginate(20);

        return $this->paginatedResponse($orders, 'Admin orders retrieved successfully');
    }

    public function show(string $id): JsonResponse
    {
        $order = Order::with(['items.product'])->find($id);
        if (!$order) {
            return $this->errorResponse('Order not found', [], 404);
        }

        return $this->successResponse($order, 'Order details retrieved successfully');
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,confirmed,preparing,out_for_delivery,delivered,cancelled',
            'payment_status' => 'nullable|in:pending,paid,refunded',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $order = Order::with('items')->find($id);
        if (!$order) {
            return $this->errorResponse('Order not found', [], 404);
        }

        $oldStatus = $order->status;
        $newStatus = $request->input('status');

        DB::transaction(function () use ($order, $oldStatus, $newStatus, $request) {
            // If cancelling an active order, restore stock
            if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                foreach ($order->items as $item) {
                    if ($item->product_id) {
                        $product = Product::lockForUpdate()->find($item->product_id);
                        if ($product) {
                            $prevQty = $product->stock_quantity;
                            $newQty = $prevQty + $item->quantity;
                            $product->update(['stock_quantity' => $newQty]);

                            StockMovement::create([
                                'product_id' => $product->id,
                                'type' => 'return',
                                'quantity' => $item->quantity,
                                'previous_quantity' => $prevQty,
                                'new_quantity' => $newQty,
                                'reference_type' => 'order_cancellation',
                                'reference_id' => $order->id,
                                'reason' => "Restocked from Cancelled Order #{$order->order_number}",
                            ]);
                        }
                    }
                }
            }

            $updateData = ['status' => $newStatus];
            if ($request->has('payment_status')) {
                $updateData['payment_status'] = $request->input('payment_status');
            } elseif ($newStatus === 'delivered') {
                $updateData['payment_status'] = 'paid';
            }

            $order->update($updateData);
        });

        return $this->successResponse($order->fresh()->load('items'), 'Order status updated successfully');
    }
}
