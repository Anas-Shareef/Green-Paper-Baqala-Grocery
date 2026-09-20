<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Api\v1\BaseApiController;
use App\Models\AdminNotification;
use App\Models\AdminPushSubscription;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminNotificationController extends BaseApiController
{
    /**
     * Get Notifications List & Unread Count for Admin Navbar & Realtime Store
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = AdminNotification::orderBy('id', 'desc')->take(30)->get();
        $unreadCount = AdminNotification::unread()->count();

        return $this->successResponse([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ], 'Admin notifications retrieved');
    }

    /**
     * Fast Realtime Sync Check for Navbar Badge & Live Toast Popups
     */
    public function realtimeCheck(Request $request): JsonResponse
    {
        try {
            $sinceId = (int) $request->input('since_id', 0);

            $allNotifications = AdminNotification::orderBy('id', 'desc')->take(30)->get();
            $newNotifications = AdminNotification::where('id', '>', $sinceId)
                ->orderBy('id', 'asc')
                ->get();

            $unreadCount = AdminNotification::unread()->count();
            $pendingOrdersCount = Order::whereIn('status', ['pending', 'awaiting_whatsapp'])->count();
            
            $latestPendingOrders = Order::whereIn('status', ['pending', 'awaiting_whatsapp'])
                ->orderBy('id', 'desc')
                ->take(10)
                ->with('items')
                ->get();

            return $this->successResponse([
                'notifications' => $allNotifications,
                'new_notifications' => $newNotifications,
                'unread_count' => $unreadCount,
                'pending_orders_count' => $pendingOrdersCount,
                'latest_pending_orders' => $latestPendingOrders,
                'latest_notification_id' => AdminNotification::max('id') ?? 0,
            ], 'Realtime check completed');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Realtime check error: ' . $e->getMessage());
            return $this->successResponse([
                'notifications' => [],
                'new_notifications' => [],
                'unread_count' => 0,
                'pending_orders_count' => 0,
                'latest_pending_orders' => [],
                'latest_notification_id' => 0,
                'error_detail' => $e->getMessage(),
            ], 'Realtime check completed with fallback');
        }
    }

    /**
     * Mark Notification as Read
     */
    public function markAsRead(string $id): JsonResponse
    {
        $notification = AdminNotification::find($id);
        if ($notification) {
            $notification->update(['is_read' => true]);
        }

        $unreadCount = AdminNotification::unread()->count();

        return $this->successResponse([
            'unread_count' => $unreadCount,
        ], 'Notification marked as read');
    }

    /**
     * Mark All Notifications as Read
     */
    public function markAllAsRead(): JsonResponse
    {
        AdminNotification::unread()->update(['is_read' => true]);

        return $this->successResponse([
            'unread_count' => 0,
        ], 'All notifications marked as read');
    }

    /**
     * Store Web Push Subscription for Mobile & Desktop Background Notifications
     */
    public function subscribePush(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'endpoint' => 'required|string',
            'public_key' => 'nullable|string',
            'auth_token' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Push subscription validation failed', $validator->errors(), 422);
        }

        $subscription = AdminPushSubscription::updateOrCreate(
            ['endpoint' => $request->input('endpoint')],
            [
                'public_key' => $request->input('public_key'),
                'auth_token' => $request->input('auth_token'),
                'device_name' => $request->input('device_name', 'Admin Device'),
                'user_agent' => $request->header('User-Agent'),
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        return $this->successResponse($subscription, 'Web Push subscription registered successfully');
    }
}
