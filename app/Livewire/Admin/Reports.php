<?php

namespace App\Livewire\Admin;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Reports extends Component
{
    public string $period = 'this_month'; // today, this_week, this_month, all_time

    public function render()
    {
        $query = Order::where('status', '!=', 'cancelled');
        $expQuery = Expense::query();

        if ($this->period === 'today') {
            $query->whereDate('created_at', now()->toDateString());
            $expQuery->whereDate('expense_date', now()->toDateString());
        } elseif ($this->period === 'this_week') {
            $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            $expQuery->whereBetween('expense_date', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($this->period === 'this_month') {
            $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
            $expQuery->whereMonth('expense_date', now()->month)->whereYear('expense_date', now()->year);
        }

        $orders = $query->get();
        $totalRevenue = (float) $orders->sum('total_amount');
        $totalProductCost = (float) $orders->sum('product_cost');
        $totalDeliveryCost = (float) $orders->sum('internal_delivery_cost');
        $totalOtherExpenses = (float) $expQuery->sum('amount');

        $grossProfit = $totalRevenue - $totalProductCost;
        $netProfit = $grossProfit - $totalDeliveryCost - $totalOtherExpenses;

        // Top Selling Products Velocity
        $topProducts = OrderItem::select('product_name', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(total) as total_revenue'), DB::raw('SUM(quantity * wholesale_cost) as total_cost'))
            ->groupBy('product_name')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        // Customer LTV Leaderboard
        $topCustomers = Customer::withCount('orders')
            ->get()
            ->sortByDesc('total_spent')
            ->take(10);

        // Inventory Valuation
        $inventoryValuationCost = Product::where('status', 'active')->sum(DB::raw('stock_quantity * wholesale_cost'));
        $inventoryValuationRetail = Product::where('status', 'active')->sum(DB::raw('stock_quantity * retail_price'));

        return view('livewire.admin.reports', [
            'totalRevenue' => $totalRevenue,
            'totalProductCost' => $totalProductCost,
            'totalDeliveryCost' => $totalDeliveryCost,
            'totalOtherExpenses' => $totalOtherExpenses,
            'grossProfit' => $grossProfit,
            'netProfit' => $netProfit,
            'ordersCount' => $orders->count(),
            'topProducts' => $topProducts,
            'topCustomers' => $topCustomers,
            'inventoryValuationCost' => $inventoryValuationCost,
            'inventoryValuationRetail' => $inventoryValuationRetail,
        ])->layout('components.layouts.app', ['title' => 'Baqqala Admin — Profit Analytics & Business Reports']);
    }
}
