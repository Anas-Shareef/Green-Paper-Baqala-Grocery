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
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();

        $todaySales = Order::whereDate('created_at', $today)
            ->where('status', '!=', 'cancelled')
            ->sum('total');

        $todayOrdersCount = Order::whereDate('created_at', $today)->count();

        $pendingOrdersCount = Order::where('status', 'pending')->count();
        $deliveredOrdersCount = Order::where('status', 'delivered')->count();

        $lowStockProducts = Product::where('status', 'active')
            ->whereColumn('stock_quantity', '<=', 'minimum_stock_level')
            ->with('category')
            ->take(10)
            ->get();

        $outOfStockCount = Product::where('status', 'active')
            ->where('stock_quantity', '<=', 0)
            ->count();

        $todayExpenses = Expense::whereDate('expense_date', $today)->sum('amount');
        $monthlyExpenses = Expense::where('expense_date', '>=', $thisMonth)->sum('amount');

        $recentOrders = Order::with('items')
            ->orderBy('id', 'desc')
            ->take(8)
            ->get();

        return $this->successResponse([
            'metrics' => [
                'today_sales' => (float) $todaySales,
                'today_orders' => $todayOrdersCount,
                'pending_orders' => $pendingOrdersCount,
                'delivered_orders' => $deliveredOrdersCount,
                'out_of_stock' => $outOfStockCount,
                'low_stock_count' => $lowStockProducts->count(),
                'today_expenses' => (float) $todayExpenses,
                'monthly_expenses' => (float) $monthlyExpenses,
            ],
            'low_stock_products' => $lowStockProducts,
            'recent_orders' => $recentOrders,
        ], 'Dashboard metrics retrieved successfully');
    }
}
