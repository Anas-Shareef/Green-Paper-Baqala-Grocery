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

            // 1 consolidated query for all order KPIs
            $orderMetrics = Order::selectRaw("
                COALESCE(SUM(CASE WHEN created_at >= ? AND created_at <= ? AND status != 'cancelled' THEN total_amount ELSE 0 END), 0) as today_sales,
                COUNT(CASE WHEN created_at >= ? AND created_at <= ? THEN 1 END) as today_orders,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
                COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_orders
            ", [$startOfDay, $endOfDay, $startOfDay, $endOfDay])->first();

            // 1 consolidated query for today + monthly expenses
            $expenseMetrics = Expense::selectRaw("
                COALESCE(SUM(CASE WHEN expense_date = ? THEN amount ELSE 0 END), 0) as today_expenses,
                COALESCE(SUM(CASE WHEN expense_date >= ? THEN amount ELSE 0 END), 0) as monthly_expenses
            ", [$todayDate, $thisMonthDate])->first();

            $lowStockProducts = Product::where('status', 'active')
                ->whereColumn('stock_quantity', '<=', 'minimum_stock_level')
                ->with('category:id,name,slug')
                ->select('id', 'category_id', 'name', 'stock_quantity', 'minimum_stock_level', 'retail_price')
                ->take(10)
                ->get();

            $outOfStockCount = Product::where('status', 'active')
                ->where('stock_quantity', '<=', 0)
                ->count();

            $recentOrders = Order::with('items:id,order_id,product_id,product_name,quantity,unit_price,total')
                ->select('id', 'order_number', 'customer_id', 'customer_villa', 'total_amount', 'status', 'payment_status', 'created_at')
                ->orderBy('id', 'desc')
                ->take(8)
                ->get();

            return [
                'metrics' => [
                    'today_sales' => (float) ($orderMetrics->today_sales ?? 0),
                    'today_orders' => (int) ($orderMetrics->today_orders ?? 0),
                    'pending_orders' => (int) ($orderMetrics->pending_orders ?? 0),
                    'delivered_orders' => (int) ($orderMetrics->delivered_orders ?? 0),
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
