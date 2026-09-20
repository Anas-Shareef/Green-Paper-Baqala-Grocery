<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Api\v1\BaseApiController;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class AdminDashboardController extends BaseApiController
{
    /**
     * Get Real-time Dashboard Analytics & Metrics
     */
    public function index(): JsonResponse
    {
        $cacheKey = 'admin_api_dashboard_metrics_v1';

        $data = \Illuminate\Support\Facades\Cache::remember($cacheKey, 15, function () {
            $startOfDay = Carbon::today()->startOfDay()->toDateTimeString();
            $endOfDay = Carbon::today()->endOfDay()->toDateTimeString();
            $todayDate = Carbon::today()->toDateString();
            $thisMonthDate = Carbon::now()->startOfMonth()->toDateString();

            // 1 consolidated query for all order KPIs and pipeline states
            $orderMetrics = Order::selectRaw("
                COALESCE(SUM(CASE WHEN created_at >= ? AND created_at <= ? AND status = 'delivered' THEN total_amount ELSE 0 END), 0) as today_sales,
                COUNT(CASE WHEN created_at >= ? AND created_at <= ? AND status NOT IN ('cancelled', 'expired') THEN 1 END) as today_orders,
                COUNT(CASE WHEN status IN ('pending', 'awaiting_whatsapp') THEN 1 END) as pending_orders,
                COUNT(CASE WHEN status IN ('confirmed', 'accepted') THEN 1 END) as confirmed_orders,
                COUNT(CASE WHEN status = 'preparing' THEN 1 END) as preparing_orders,
                COUNT(CASE WHEN status = 'ready' THEN 1 END) as ready_orders,
                COUNT(CASE WHEN status = 'out_for_delivery' THEN 1 END) as out_for_delivery_orders,
                COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_orders,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders
            ", [$startOfDay, $endOfDay, $startOfDay, $endOfDay])->first();

            // 1 consolidated query for today + monthly expenses
            $expenseMetrics = Expense::selectRaw("
                COALESCE(SUM(CASE WHEN expense_date = ? THEN amount ELSE 0 END), 0) as today_expenses,
                COALESCE(SUM(CASE WHEN expense_date >= ? THEN amount ELSE 0 END), 0) as monthly_expenses
            ", [$todayDate, $thisMonthDate])->first();

            $lowStockProducts = Product::where('status', 'active')
                ->whereRaw('(stock_quantity - COALESCE(reserved_quantity, 0)) <= minimum_stock_level')
                ->with('category:id,name,slug')
                ->select('id', 'category_id', 'name', 'stock_quantity', 'reserved_quantity', 'minimum_stock_level', 'retail_price')
                ->take(10)
                ->get();

            $outOfStockCount = Product::where('status', 'active')
                ->whereRaw('(stock_quantity - COALESCE(reserved_quantity, 0)) <= 0')
                ->count();

            $recentOrders = Order::with('items:id,order_id,product_id,product_name,quantity,unit_price,total')
                ->select('id', 'order_number', 'customer_id', 'customer_order_number', 'customer_villa', 'total_amount', 'status', 'payment_status', 'created_at')
                ->orderBy('id', 'desc')
                ->take(8)
                ->get();

            return [
                'metrics' => [
                    'today_sales' => (float) ($orderMetrics->today_sales ?? 0),
                    'today_orders' => (int) ($orderMetrics->today_orders ?? 0),
                    'pending_orders' => (int) ($orderMetrics->pending_orders ?? 0),
                    'confirmed_orders' => (int) ($orderMetrics->confirmed_orders ?? 0),
                    'preparing_orders' => (int) ($orderMetrics->preparing_orders ?? 0),
                    'ready_orders' => (int) ($orderMetrics->ready_orders ?? 0),
                    'out_for_delivery_orders' => (int) ($orderMetrics->out_for_delivery_orders ?? 0),
                    'delivered_orders' => (int) ($orderMetrics->delivered_orders ?? 0),
                    'cancelled_orders' => (int) ($orderMetrics->cancelled_orders ?? 0),
                    'out_of_stock' => (int) $outOfStockCount,
                    'low_stock_count' => $lowStockProducts->count(),
                    'today_expenses' => (float) ($expenseMetrics->today_expenses ?? 0),
                    'monthly_expenses' => (float) ($expenseMetrics->monthly_expenses ?? 0),
                ],
                'low_stock_products' => $lowStockProducts,
                'recent_orders' => $recentOrders,
            ];
        });

        return $this->successResponse($data, 'Dashboard metrics retrieved successfully');
    }
}
