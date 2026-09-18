<?php

namespace App\Livewire\Admin;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use Livewire\Component;

class Dashboard extends Component
{
    protected $listeners = ['orderReceived' => '$refresh'];

    public function acceptOrder(int $orderId, OrderService $orderService)
    {
        $order = Order::findOrFail($orderId);
        $orderService->acceptOrder($order);
        session()->flash('message', "Order #{$order->order_number} marked as ACCEPTED.");
    }

    public function deliverOrder(int $orderId, OrderService $orderService)
    {
        $order = Order::findOrFail($orderId);
        $orderService->deliverOrder($order);
        session()->flash('message', "Order #{$order->order_number} marked as DELIVERED.");
    }

    public function render()
    {
        $startOfDay = now()->startOfDay()->toDateTimeString();
        $endOfDay = now()->endOfDay()->toDateTimeString();
        $todayDate = now()->toDateString();

        // 1 consolidated query for all today's order metrics (replaces 5 queries)
        $orderStats = Order::whereBetween('created_at', [$startOfDay, $endOfDay])
            ->selectRaw("
                COUNT(CASE WHEN status != 'cancelled' THEN 1 END) as today_orders_count,
                COALESCE(SUM(CASE WHEN status != 'cancelled' THEN total_amount ELSE 0 END), 0) as today_revenue,
                COALESCE(SUM(CASE WHEN status != 'cancelled' THEN product_cost ELSE 0 END), 0) as today_product_cost,
                COALESCE(SUM(CASE WHEN status != 'cancelled' THEN internal_delivery_cost ELSE 0 END), 0) as today_delivery_cost,
                COUNT(CASE WHEN order_source = 'PWA' THEN 1 END) as delivery_orders_count,
                COUNT(CASE WHEN order_source = 'POS' THEN 1 END) as walk_in_orders_count
            ")->first();

        $todayRevenue = (float) ($orderStats->today_revenue ?? 0);
        $todayOrdersCount = (int) ($orderStats->today_orders_count ?? 0);
        $todayProductCost = (float) ($orderStats->today_product_cost ?? 0);
        $todayDeliveryCost = (float) ($orderStats->today_delivery_cost ?? 0);
        $deliveryOrdersCount = (int) ($orderStats->delivery_orders_count ?? 0);
        $walkInOrdersCount = (int) ($orderStats->walk_in_orders_count ?? 0);

        $todayOtherExpenses = (float) Expense::where('expense_date', $todayDate)->sum('amount');

        $todayGrossProfit = $todayRevenue - $todayProductCost;
        $todayNetProfit = $todayGrossProfit - $todayDeliveryCost - $todayOtherExpenses;

        $pendingOrders = Order::with('customer:id,name')
            ->where('status', 'pending')
            ->select('id', 'order_number', 'customer_id', 'customer_villa', 'total_amount', 'payment_method')
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get();

        $recentOrders = Order::with('customer:id,name')
            ->select('id', 'order_number', 'customer_id', 'customer_villa', 'order_source', 'total_amount', 'product_cost', 'net_profit', 'status', 'created_at')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        // 30s cache for stock stats (replaces 2 table scans)
        $stockStats = \Illuminate\Support\Facades\Cache::remember('admin_dash_stock_stats', 30, function () {
            return Product::where('status', 'active')
                ->selectRaw("
                    COUNT(CASE WHEN stock_quantity <= minimum_stock_level AND stock_quantity > 0 THEN 1 END) as low_stock,
                    COUNT(CASE WHEN stock_quantity <= 0 THEN 1 END) as out_of_stock
                ")->first();
        });

        $lowStockCount = (int) ($stockStats->low_stock ?? 0);
        $outOfStockCount = (int) ($stockStats->out_of_stock ?? 0);

        // 30s cache for customer counts (replaces 2 customer table scans)
        $customerStats = \Illuminate\Support\Facades\Cache::remember('admin_dash_cust_stats_' . $todayDate, 30, function () use ($startOfDay, $endOfDay) {
            return Customer::selectRaw("
                COUNT(*) as total_customers,
                COUNT(CASE WHEN created_at >= ? AND created_at <= ? THEN 1 END) as new_customers
            ", [$startOfDay, $endOfDay])->first();
        });

        $newCustomersCount = (int) ($customerStats->new_customers ?? 0);
        $totalCustomersCount = (int) ($customerStats->total_customers ?? 0);

        return view('livewire.admin.dashboard', [
            'todayRevenue' => $todayRevenue,
            'todayOrdersCount' => $todayOrdersCount,
            'todayProductCost' => $todayProductCost,
            'todayDeliveryCost' => $todayDeliveryCost,
            'todayGrossProfit' => $todayGrossProfit,
            'todayNetProfit' => $todayNetProfit,
            'todayOtherExpenses' => $todayOtherExpenses,
            'pendingOrdersCount' => $pendingOrders->count(),
            'pendingOrders' => $pendingOrders,
            'recentOrders' => $recentOrders,
            'lowStockCount' => $lowStockCount,
            'outOfStockCount' => $outOfStockCount,
            'newCustomersCount' => $newCustomersCount,
            'totalCustomersCount' => $totalCustomersCount,
            'deliveryOrdersCount' => $deliveryOrdersCount,
            'walkInOrdersCount' => $walkInOrdersCount,
        ])->layout('components.layouts.app', ['title' => 'Baqqala Admin — Dashboard & Net Profit']);
    }
}
