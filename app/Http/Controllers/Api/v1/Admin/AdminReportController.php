<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Api\v1\BaseApiController;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminReportController extends BaseApiController
{
    /**
     * Generate Comprehensive Financial & Sales Reports
     */
    public function index(Request $request): JsonResponse
    {
        $period = $request->input('period', 'this_month');
        $startDate = match ($period) {
            'today' => Carbon::today(),
            'yesterday' => Carbon::yesterday(),
            'this_week' => Carbon::now()->startOfWeek(),
            'custom' => $request->input('start_date') ? Carbon::parse($request->input('start_date')) : Carbon::now()->startOfMonth(),
            default => Carbon::now()->startOfMonth(),
        };

        $endDate = match ($period) {
            'yesterday' => Carbon::yesterday()->endOfDay(),
            'custom' => $request->input('end_date') ? Carbon::parse($request->input('end_date'))->endOfDay() : Carbon::now()->endOfDay(),
            default => Carbon::now()->endOfDay(),
        };

        // Finalized Sales Query (Delivered orders represent authoritative finalized revenue per PRD Section 58)
        $deliveredSalesQuery = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'delivered');

        $totalRevenue = (float) $deliveredSalesQuery->sum('total_amount');
        $deliveredOrders = $deliveredSalesQuery->count();

        // Operational Pipeline Counts
        $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->whereNotIn('status', ['cancelled', 'expired'])
            ->count();
        $confirmedOrders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['confirmed', 'accepted', 'preparing', 'ready', 'out_for_delivery'])
            ->count();
        $pendingOrders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['pending', 'awaiting_whatsapp'])
            ->count();

        $totalExpenses = (float) Expense::whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()])->sum('amount');

        // Estimate Cost of Goods (70% heuristic or actual cost where available)
        $costOfGoods = $totalRevenue * 0.70;
        $grossProfit = $totalRevenue - $costOfGoods;
        $netProfit = $grossProfit - $totalExpenses;

        $topProducts = Order::whereBetween('orders.created_at', [$startDate, $endDate])
            ->where('orders.status', 'delivered')
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->select('order_items.product_name', \Illuminate\Support\Facades\DB::raw('SUM(order_items.quantity) as total_qty'), \Illuminate\Support\Facades\DB::raw('SUM(order_items.total) as total_sales'))
            ->groupBy('order_items.product_name')
            ->orderBy('total_sales', 'desc')
            ->take(5)
            ->get();

        return $this->successResponse([
            'period' => $period,
            'date_range' => [
                'start' => $startDate->toDateTimeString(),
                'end' => $endDate->toDateTimeString(),
            ],
            'summary' => [
                'total_revenue' => round($totalRevenue, 2),
                'delivered_orders' => $deliveredOrders,
                'total_orders' => $totalOrders,
                'confirmed_orders' => $confirmedOrders,
                'pending_orders' => $pendingOrders,
                'total_expenses' => round($totalExpenses, 2),
                'estimated_cogs' => round($costOfGoods, 2),
                'gross_profit' => round($grossProfit, 2),
                'net_profit' => round($netProfit, 2),
            ],
            'top_products' => $topProducts,
        ], 'Financial and sales report generated successfully');
    }
}
