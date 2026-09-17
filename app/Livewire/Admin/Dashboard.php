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
        $today = now()->toDateString();

        $todayOrders = Order::whereDate('created_at', $today)->where('status', '!=', 'cancelled')->get();

        $todayRevenue = (float) $todayOrders->sum('total_amount');
        $todayProductCost = (float) $todayOrders->sum('product_cost');
        $todayDeliveryCost = (float) $todayOrders->sum('internal_delivery_cost');
        $todayOtherExpenses = (float) Expense::whereDate('expense_date', $today)->sum('amount');

        $todayGrossProfit = $todayRevenue - $todayProductCost;
        $todayNetProfit = $todayGrossProfit - $todayDeliveryCost - $todayOtherExpenses;

        $pendingOrders = Order::where('status', 'pending')->orderBy('id', 'desc')->get();
        $recentOrders = Order::with('customer')->orderBy('id', 'desc')->limit(10)->get();

        $lowStockCount = Product::where('status', 'active')
            ->whereRaw('stock_quantity <= minimum_stock_level AND stock_quantity > 0')
            ->count();

        $outOfStockCount = Product::where('status', 'active')
            ->where('stock_quantity', '<=', 0)
            ->count();

        $deliveryOrdersCount = Order::whereDate('created_at', $today)->where('order_source', 'PWA')->count();
        $walkInOrdersCount = Order::whereDate('created_at', $today)->where('order_source', 'POS')->count();

        return view('livewire.admin.dashboard', [
            'todayRevenue' => $todayRevenue,
            'todayOrdersCount' => $todayOrders->count(),
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
            'newCustomersCount' => Customer::whereDate('created_at', $today)->count(),
            'totalCustomersCount' => Customer::count(),
            'deliveryOrdersCount' => $deliveryOrdersCount,
            'walkInOrdersCount' => $walkInOrdersCount,
        ])->layout('components.layouts.app', ['title' => 'Baqqala Admin — Dashboard & Net Profit']);
    }
}
