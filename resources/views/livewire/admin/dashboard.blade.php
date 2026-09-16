<div class="p-6 space-y-6">
    
    <!-- Page Header Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200/80 pb-5">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                Operational Dashboard & Profit Engine
                <span class="badge badge-emerald">Live Source of Truth</span>
            </h1>
            <p class="text-xs text-slate-500 mt-1">Real-time revenue, wholesale product costs, internal delivery expenses, and Net Profit formula.</p>
        </div>
        
        <div class="flex items-center gap-3">
            <a href="/pos" class="btn-glow">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                Launch Barcode POS Counter
            </a>
        </div>
    </div>

    @if(session()->has('message'))
    <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm font-semibold">
        {{ session('message') }}
    </div>
    @endif

    <!-- LUCA WORLD SIGNATURE KPI CARDS GRID (Light Mode) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
        
        <!-- Today Sales -->
        <div class="kpi-card space-y-1">
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Today's Revenue</div>
            <div class="text-2xl font-black text-emerald-600 font-mono">₹{{ number_format($todayRevenue, 2) }}</div>
            <div class="text-[10px] text-slate-400 font-semibold">{{ $todayOrdersCount }} Total Orders Today</div>
        </div>

        <!-- Today Wholesale Cost -->
        <div class="kpi-card space-y-1">
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Wholesale Cost</div>
            <div class="text-2xl font-black text-rose-600 font-mono">₹{{ number_format($todayProductCost, 2) }}</div>
            <div class="text-[10px] text-slate-400 font-semibold">Cost of Goods Sold</div>
        </div>

        <!-- Today Delivery Cost -->
        <div class="kpi-card space-y-1">
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Internal Delivery Cost</div>
            <div class="text-2xl font-black text-amber-600 font-mono">₹{{ number_format($todayDeliveryCost, 2) }}</div>
            <div class="text-[10px] text-slate-400 font-semibold">₹25/order delivery expense</div>
        </div>

        <!-- Today Net Profit -->
        <div class="kpi-card bg-gradient-to-br from-emerald-600 to-teal-700 text-white space-y-1">
            <div class="text-[11px] font-black text-emerald-100 uppercase tracking-wider">Today's Net Profit</div>
            <div class="text-2xl font-black text-white font-mono">₹{{ number_format($todayNetProfit, 2) }}</div>
            <div class="text-[10px] text-emerald-100 font-bold">Revenue - Cost - Delivery - Expenses</div>
        </div>

        <!-- Pending Orders Alert -->
        <div class="kpi-card space-y-1">
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Pending Orders</div>
            <div class="text-2xl font-black text-amber-600 font-mono">{{ $pendingOrdersCount }}</div>
            <div class="text-[10px] text-slate-400 font-semibold">Awaiting Acceptance</div>
        </div>

        <!-- Low Stock Counter -->
        <div class="kpi-card space-y-1">
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Low Stock Items</div>
            <div class="text-2xl font-black text-amber-500 font-mono">{{ $lowStockCount }}</div>
            <div class="text-[10px] text-slate-400 font-semibold">At or below reorder level</div>
        </div>

        <!-- Out of Stock Counter -->
        <div class="kpi-card space-y-1">
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Out of Stock</div>
            <div class="text-2xl font-black text-rose-600 font-mono">{{ $outOfStockCount }}</div>
            <div class="text-[10px] text-slate-400 font-semibold">Disabled on Customer PWA</div>
        </div>

        <!-- Sales Source Breakdown -->
        <div class="kpi-card space-y-1">
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Delivery vs Walk-in</div>
            <div class="text-lg font-black text-slate-900 font-mono flex items-center justify-between mt-1">
                <span class="text-teal-600">{{ $deliveryOrdersCount }} PWA</span>
                <span class="text-slate-300">/</span>
                <span class="text-emerald-600">{{ $walkInOrdersCount }} POS</span>
            </div>
            <div class="text-[10px] text-slate-400 font-semibold">80% Delivery target ratio</div>
        </div>
    </div>

    <!-- PENDING ONLINE ORDERS ACTIONS BOARD -->
    @if($pendingOrders->count() > 0)
    <div class="bg-amber-50/80 border border-amber-200/80 rounded-2xl p-5 space-y-4 shadow-xs">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="w-3 h-3 rounded-full bg-amber-500 animate-ping"></span>
                <h3 class="font-extrabold text-slate-900 text-base">Pending Orders Requiring Action ({{ $pendingOrders->count() }})</h3>
            </div>
            <a href="/admin/orders" class="text-xs text-amber-700 hover:underline font-bold">View All Orders &rarr;</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($pendingOrders as $pOrder)
            <div class="p-4 bg-white border border-slate-200 rounded-xl space-y-3 shadow-xs">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="font-extrabold text-slate-900 text-sm font-mono">{{ $pOrder->order_number }}</div>
                        <div class="text-xs text-slate-600 font-medium mt-0.5">{{ $pOrder->customer?->name ?? 'Online Customer' }}</div>
                        <div class="text-xs text-amber-700 font-bold">Villa: {{ $pOrder->customer_villa ?? 'N/A' }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-black text-emerald-600 font-mono">₹{{ number_format($pOrder->total_amount, 2) }}</div>
                        <div class="text-[10px] text-slate-400 font-bold uppercase">{{ $pOrder->payment_method }}</div>
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
                    <button wire:click="acceptOrder({{ $pOrder->id }})" class="btn-glow w-full justify-center text-xs">
                        Accept & Reserve Stock
                    </button>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- LUCA WORLD RECENT ORDERS DATA TABLE (Light Mode) -->
    <div class="bg-white border border-slate-200/80 rounded-2xl p-5 space-y-4 shadow-xs">
        <div class="flex items-center justify-between">
            <h3 class="font-extrabold text-slate-900 text-base">Recent Sales & Delivery Orders</h3>
            <a href="/admin/orders" class="text-xs text-emerald-600 hover:underline font-bold">View Full Order History &rarr;</a>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-100">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="tbl-head">Order No</th>
                        <th class="tbl-head">Source</th>
                        <th class="tbl-head">Customer / Villa</th>
                        <th class="tbl-head text-right">Revenue</th>
                        <th class="tbl-head text-right">Product Cost</th>
                        <th class="tbl-head text-right">Net Profit</th>
                        <th class="tbl-head text-center">Status</th>
                        <th class="tbl-head text-right">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @foreach($recentOrders as $ro)
                    <tr class="tbl-row">
                        <td class="tbl-cell font-mono font-bold text-slate-900">{{ $ro->order_number }}</td>
                        <td class="tbl-cell">
                            <span class="badge {{ $ro->order_source === 'POS' ? 'badge-emerald' : 'badge-slate' }}">
                                {{ $ro->order_source }}
                            </span>
                        </td>
                        <td class="tbl-cell">
                            <div class="font-bold text-slate-900">{{ $ro->customer?->name ?? 'Walk-in Cashier' }}</div>
                            <div class="text-[10px] text-slate-500">{{ $ro->customer_villa ?? 'POS Counter' }}</div>
                        </td>
                        <td class="tbl-cell text-right font-mono font-bold text-slate-900">₹{{ number_format($ro->total_amount, 2) }}</td>
                        <td class="tbl-cell text-right font-mono text-slate-500">₹{{ number_format($ro->product_cost, 2) }}</td>
                        <td class="tbl-cell text-right font-mono font-black text-emerald-600">₹{{ number_format($ro->net_profit, 2) }}</td>
                        <td class="tbl-cell text-center">
                            @if($ro->status === 'delivered')
                            <span class="badge badge-emerald">{{ $ro->status }}</span>
                            @elseif($ro->status === 'pending')
                            <span class="badge badge-amber">{{ $ro->status }}</span>
                            @elseif($ro->status === 'cancelled')
                            <span class="badge badge-rose">{{ $ro->status }}</span>
                            @else
                            <span class="badge badge-slate">{{ $ro->status }}</span>
                            @endif
                        </td>
                        <td class="tbl-cell text-right text-slate-500 text-[11px] font-mono">{{ $ro->created_at->format('H:i') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
