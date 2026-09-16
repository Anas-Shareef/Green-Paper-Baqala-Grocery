<div class="p-6 space-y-6">
    
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                Profit Analytics & Financial Statements
                <span class="badge badge-emerald">Net Profit Engine</span>
            </h1>
            <p class="text-xs text-slate-500 mt-1">Audit margin leakage: Revenue - Wholesale Cost - Internal Delivery - Expenses = Net Profit.</p>
        </div>

        <div class="flex items-center gap-2 font-bold text-xs">
            @foreach(['today' => 'Today', 'this_week' => 'This Week', 'this_month' => 'This Month', 'all_time' => 'All Time'] as $pKey => $pLabel)
            <button wire:click="$set('period', '{{ $pKey }}')" class="px-3.5 py-2 rounded-xl transition-all {{ $period === $pKey ? 'bg-emerald-600 text-white font-black shadow-md shadow-emerald-600/20' : 'bg-slate-100 text-slate-600 hover:text-slate-900 hover:bg-slate-200 border border-slate-200' }}">
                {{ $pLabel }}
            </button>
            @endforeach
        </div>
    </div>

    <!-- P&L Financial Statement Card -->
    <div class="kpi-card p-6 space-y-4">
        <h3 class="font-extrabold text-slate-900 text-lg">Profit & Loss (P&L) Statement</h3>

        <div class="space-y-3 font-mono text-sm max-w-xl">
            <div class="flex justify-between items-center text-slate-900 font-bold p-3 bg-slate-50 rounded-xl border border-slate-200">
                <span>Total Retail Revenue (Sales):</span>
                <span class="text-emerald-700 text-lg">₹{{ number_format($totalRevenue, 2) }}</span>
            </div>

            <div class="flex justify-between items-center text-rose-700 p-3 bg-slate-50 rounded-xl border border-slate-200">
                <span>- Cost of Goods Sold (Wholesale Cost):</span>
                <span>-₹{{ number_format($totalProductCost, 2) }}</span>
            </div>

            <div class="flex justify-between items-center text-slate-900 font-extrabold p-3 bg-slate-100 rounded-xl border border-slate-300">
                <span>= GROSS PRODUCT PROFIT:</span>
                <span class="text-emerald-700 text-base">₹{{ number_format($grossProfit, 2) }}</span>
            </div>

            <div class="flex justify-between items-center text-amber-700 p-3 bg-slate-50 rounded-xl border border-slate-200">
                <span>- Internal Delivery Costs (₹25/order):</span>
                <span>-₹{{ number_format($totalDeliveryCost, 2) }}</span>
            </div>

            <div class="flex justify-between items-center text-rose-700 p-3 bg-slate-50 rounded-xl border border-slate-200">
                <span>- Other Operating Expenses:</span>
                <span>-₹{{ number_format($totalOtherExpenses, 2) }}</span>
            </div>

            <div class="flex justify-between items-center text-white font-black text-xl p-4 bg-gradient-to-r from-emerald-600 to-teal-600 rounded-xl shadow-lg shadow-emerald-600/20">
                <span>= ACTUAL NET PROFIT:</span>
                <span>₹{{ number_format($netProfit, 2) }}</span>
            </div>
        </div>
    </div>

    <!-- 2 Column Breakdown: Top Products Velocity & Inventory Valuation -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Top Products Sales Velocity -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 space-y-4 shadow-sm">
            <h3 class="font-extrabold text-slate-900 text-base">Top Selling Products Velocity</h3>

            <table class="w-full text-left text-xs text-slate-700">
                <thead class="tbl-head">
                    <tr>
                        <th class="p-3">Product Name</th>
                        <th class="p-3 text-center">Units Sold</th>
                        <th class="p-3 text-right">Revenue</th>
                        <th class="p-3 text-right font-mono text-emerald-700">Profit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @foreach($topProducts as $tp)
                    <tr class="tbl-row">
                        <td class="tbl-cell font-bold text-slate-900">{{ $tp->product_name }}</td>
                        <td class="tbl-cell text-center font-mono font-bold text-amber-700">{{ $tp->total_qty }}</td>
                        <td class="tbl-cell text-right font-mono text-slate-700">₹{{ number_format($tp->total_revenue, 2) }}</td>
                        <td class="tbl-cell text-right font-mono font-bold text-emerald-700">₹{{ number_format($tp->total_revenue - $tp->total_cost, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Inventory Valuation -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 space-y-4 shadow-sm">
            <h3 class="font-extrabold text-slate-900 text-base">Current Inventory Valuation</h3>

            <div class="space-y-3 font-mono text-xs">
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 flex justify-between items-center">
                    <div>
                        <div class="text-slate-500 text-[10px] uppercase font-bold tracking-wider">Wholesale Stock Cost Value</div>
                        <div class="text-xl font-extrabold text-rose-700 mt-1">₹{{ number_format($inventoryValuationCost, 2) }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-slate-500 text-[10px] uppercase font-bold tracking-wider">Retail Realizable Value</div>
                        <div class="text-xl font-extrabold text-emerald-700 mt-1">₹{{ number_format($inventoryValuationRetail, 2) }}</div>
                    </div>
                </div>

                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200">
                    <div class="text-slate-500 text-[10px] uppercase font-bold tracking-wider">Unrealized Inventory Gross Margin:</div>
                    <div class="text-2xl font-black text-emerald-700 mt-1">₹{{ number_format($inventoryValuationRetail - $inventoryValuationCost, 2) }}</div>
                </div>
            </div>
        </div>

    </div>

</div>
