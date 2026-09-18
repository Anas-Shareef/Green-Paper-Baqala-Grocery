<div class="p-6 space-y-6">
    
    <!-- PAGE HEADER BAR -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200/80 pb-5">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                Inventory Control Center & Stock Ledger
                <span class="badge badge-emerald">Real-time Balance</span>
            </h1>
            <p class="text-xs text-slate-500 mt-1">Single source of truth for physical stock, reservations, ledger audit, expiry, reorder recommendations, and valuation.</p>
        </div>
        
        <div class="flex flex-wrap items-center gap-2.5">
            <button wire:click="createProduct" class="btn-glow py-2 px-3.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-xs flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                + Add Product
            </button>

            <button wire:click="$set('showImportModal', true)" class="btn-outline py-2 px-3.5 text-xs font-bold text-slate-700 hover:bg-slate-100 rounded-xl border border-slate-300 flex items-center gap-1.5 bg-white shadow-xs">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                Import
            </button>

            <button wire:click="exportCsv" class="btn-outline py-2 px-3.5 text-xs font-bold text-slate-700 hover:bg-slate-100 rounded-xl border border-slate-300 flex items-center gap-1.5 bg-white shadow-xs">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Export
            </button>
        </div>
    </div>

    <!-- FLASH NOTIFICATIONS -->
    @if(session()->has('message'))
    <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-sm font-semibold flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            {{ session('message') }}
        </div>
        <button type="button" wire:click="$refresh" class="text-emerald-700 hover:text-emerald-900 text-xs font-bold">&times;</button>
    </div>
    @endif
    @if(session()->has('error'))
    <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl text-sm font-semibold flex items-center gap-2 shadow-xs">
        <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        {{ session('error') }}
    </div>
    @endif

    <!-- NEGATIVE STOCK INTEGRITY WARNING -->
    @if(($kpis['negative_stock_count'] ?? 0) > 0)
    <div class="p-4 bg-rose-100 border-2 border-rose-400 text-rose-900 rounded-2xl text-xs font-bold flex items-center justify-between shadow-sm">
        <div class="flex items-center gap-2.5">
            <span class="w-3 h-3 rounded-full bg-rose-600 animate-ping"></span>
            <span>DATA INTEGRITY ALERT: {{ $kpis['negative_stock_count'] }} product(s) have fallen below zero physical stock!</span>
        </div>
        <button wire:click="$set('stockFilter', 'negative')" class="underline hover:text-rose-950 font-extrabold">View Negative Stock &rarr;</button>
    </div>
    @endif

    <!-- INVENTORY KPI CARDS GRID -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5">
        
        <!-- Total Products -->
        <div class="kpi-card space-y-1 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Products</div>
            <div class="text-2xl font-black text-slate-900 font-mono">{{ number_format($kpis['total_products']) }}</div>
            <div class="text-[10px] text-slate-400 font-semibold">Active catalog items</div>
        </div>

        <!-- Total Physical Units -->
        <div class="kpi-card space-y-1 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Units</div>
            <div class="text-2xl font-black text-teal-600 font-mono">{{ number_format($kpis['total_units']) }}</div>
            <div class="text-[10px] text-slate-400 font-semibold">Physical items in store</div>
        </div>

        <!-- Inventory Cost Valuation -->
        <div class="kpi-card space-y-1 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Cost Value (AED)</div>
            <div class="text-2xl font-black text-rose-600 font-mono">AED {{ number_format($kpis['cost_value'], 2) }}</div>
            <div class="text-[10px] text-slate-400 font-semibold">Stock × Wholesale Cost</div>
        </div>

        <!-- Retail Value & Potential Margin -->
        <div class="kpi-card space-y-1 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Retail Value</div>
            <div class="text-2xl font-black text-emerald-600 font-mono">AED {{ number_format($kpis['retail_value'], 2) }}</div>
            <div class="text-[10px] text-emerald-600 font-bold">+AED {{ number_format($kpis['potential_margin'], 2) }} Margin</div>
        </div>

        <!-- Low Stock Counter -->
        <button wire:click="$set('stockFilter', 'low_stock')" class="kpi-card text-left space-y-1 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs hover:border-amber-400 transition-all">
            <div class="text-[11px] font-bold text-amber-600 uppercase tracking-wider flex items-center justify-between">
                <span>Low Stock</span>
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
            </div>
            <div class="text-2xl font-black text-amber-600 font-mono">{{ $kpis['low_stock_count'] }}</div>
            <div class="text-[10px] text-slate-400 font-semibold">Below reorder level</div>
        </button>

        <!-- Out of Stock / Expiring Soon -->
        <button wire:click="$set('stockFilter', 'out_of_stock')" class="kpi-card text-left space-y-1 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs hover:border-rose-400 transition-all">
            <div class="text-[11px] font-bold text-rose-600 uppercase tracking-wider flex items-center justify-between">
                <span>Out of Stock</span>
                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
            </div>
            <div class="text-2xl font-black text-rose-600 font-mono">{{ $kpis['out_of_stock_count'] }}</div>
            <div class="text-[10px] text-slate-400 font-semibold">{{ $kpis['expiring_soon_count'] }} expiring &le; 7d</div>
        </button>

    </div>

    <!-- MAIN MODULE TABS NAVIGATION -->
    <div class="flex items-center gap-2 border-b border-slate-200 text-xs font-bold">
        <button wire:click="setTab('stock')" class="pb-3 px-4 transition-all flex items-center gap-2 border-b-2 {{ $activeTab === 'stock' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
            Products
            <span class="badge {{ $activeTab === 'stock' ? 'badge-emerald' : 'badge-slate' }}">{{ $products->total() }}</span>
        </button>

        <button wire:click="setTab('categories')" class="pb-3 px-4 transition-all flex items-center gap-2 border-b-2 {{ $activeTab === 'categories' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
            Categories
            <span class="badge badge-slate">{{ $categories->count() }}</span>
        </button>

        <button wire:click="setTab('movements')" class="pb-3 px-4 transition-all flex items-center gap-2 border-b-2 {{ $activeTab === 'movements' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
            Stock Movements
            <span class="badge badge-slate">Audit Trail</span>
        </button>

        <button wire:click="setTab('counts')" class="pb-3 px-4 transition-all flex items-center gap-2 border-b-2 {{ $activeTab === 'counts' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Physical Stock Counts
            <span class="badge badge-amber">{{ $counts->count() }} Sessions</span>
        </button>

        <button wire:click="setTab('reorder')" class="pb-3 px-4 transition-all flex items-center gap-2 border-b-2 {{ $activeTab === 'reorder' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            Reorder Suggestions
            @if($kpis['low_stock_count'] > 0)
            <span class="badge badge-rose">{{ $kpis['low_stock_count'] }}</span>
            @endif
        </button>

        <button wire:click="setTab('valuation')" class="pb-3 px-4 transition-all flex items-center gap-2 border-b-2 {{ $activeTab === 'valuation' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-slate-500 hover:text-slate-900' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            Category Valuation
        </button>
    </div>

    <!-- TAB 1: STOCK ITEMS TABLE -->
    @if($activeTab === 'stock')
    <div class="space-y-4">
        <!-- Filters Bar -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs text-xs">
            <!-- Search -->
            <div class="col-span-1 sm:col-span-2">
                <input type="text" wire:model.live.debounce.400ms="search" placeholder="Search by name, barcode e.g. 8901288030609, SKU, brand..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-500 font-medium">
            </div>

            <!-- Category -->
            <div>
                <select wire:model.live="selectedCategory" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 outline-none focus:border-emerald-500 font-medium">
                    <option value="">All Categories</option>
                    @foreach($categories as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Stock Status -->
            <div>
                <select wire:model.live="stockFilter" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 outline-none focus:border-emerald-500 font-medium">
                    <option value="">All Stock Levels</option>
                    <option value="in_stock">In Stock (> Reorder)</option>
                    <option value="low_stock">Low Stock Warning</option>
                    <option value="out_of_stock">Out of Stock (&le; 0)</option>
                    <option value="negative">Negative Stock Integrity</option>
                    <option value="overstocked">Overstocked (> Max)</option>
                </select>
            </div>

            <!-- Expiry Filter -->
            <div>
                <select wire:model.live="expiryFilter" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 outline-none focus:border-emerald-500 font-medium">
                    <option value="">All Expiries</option>
                    <option value="expired">Expired</option>
                    <option value="within_3_days">Expires &le; 3 Days</option>
                    <option value="within_7_days">Expires &le; 7 Days</option>
                    <option value="within_30_days">Expires &le; 30 Days</option>
                </select>
            </div>
        </div>

        <!-- Main Stock Data Table -->
        <div class="bg-white border border-slate-200/80 rounded-2xl overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="tbl-head bg-slate-50 border-b border-slate-200 font-bold uppercase tracking-wider text-[11px] text-slate-500">
                        <tr>
                            <th class="p-3.5 w-10 text-center" wire:click.stop>
                                <input type="checkbox" wire:model.live="selectAll" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            </th>
                            <th class="p-3.5">Product & SKU</th>
                            <th class="p-3.5">Category</th>
                            <th class="p-3.5 text-right">Wholesale</th>
                            <th class="p-3.5 text-right">Selling</th>
                            <th class="p-3.5 text-right">Margin</th>
                            <th class="p-3.5 text-center">Stock</th>
                            <th class="p-3.5 text-center">Reserved</th>
                            <th class="p-3.5 text-center">Available</th>
                            <th class="p-3.5 text-center">Reorder</th>
                            <th class="p-3.5 text-center">Status</th>
                            <th class="p-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        @forelse($products as $p)
                        @php
                            $available = max(0, $p->stock_quantity - $p->reserved_quantity);
                            $isNegative = $p->stock_quantity < 0;
                            $isOutOfStock = $available <= 0;
                            $isLowStock = $available <= $p->minimum_stock_level && !$isOutOfStock;
                            $margin = (float) $p->retail_price - (float) $p->wholesale_cost;
                            $marginPct = (float) $p->retail_price > 0 ? round(($margin / (float) $p->retail_price) * 100, 1) : 0;
                        @endphp
                        <tr class="tbl-row hover:bg-slate-50/80 cursor-pointer {{ in_array((string)$p->id, $selectedProductIds) ? 'bg-emerald-50/40' : '' }}" wire:click="openDrawer({{ $p->id }})">
                            <!-- Select Checkbox -->
                            <td class="p-3.5 text-center" wire:click.stop>
                                <input type="checkbox" wire:model.live="selectedProductIds" value="{{ $p->id }}" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            </td>

                            <!-- Product Details -->
                            <td class="tbl-cell">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 shrink-0 overflow-hidden flex items-center justify-center">
                                        @if($p->image_url)
                                        <img src="{{ $p->image_url }}" alt="{{ $p->name }}" class="w-full h-full object-cover">
                                        @elseif($p->image)
                                        <img src="{{ $p->image }}" alt="{{ $p->name }}" class="w-full h-full object-cover">
                                        @else
                                        <span class="text-slate-400 font-black text-xs">{{ substr($p->name, 0, 2) }}</span>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900 text-sm hover:text-emerald-600 transition-colors">{{ $p->name }}</div>
                                        <div class="text-[10px] text-slate-500 font-mono mt-0.5 flex items-center gap-2">
                                            <span class="text-amber-700 font-bold">{{ $p->barcode }}</span>
                                            @if($p->sku)
                                            <span class="text-slate-400">• SKU: {{ $p->sku }}</span>
                                            @endif
                                            <span>• {{ $p->unit }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Category -->
                            <td class="tbl-cell">
                                <span class="badge badge-amber font-semibold">{{ $p->category?->name ?? 'Uncategorized' }}</span>
                            </td>

                            <!-- Wholesale Cost -->
                            <td class="tbl-cell text-right font-mono text-rose-600 font-bold">
                                AED {{ number_format($p->wholesale_cost, 2) }}
                            </td>

                            <!-- Selling Price -->
                            <td class="tbl-cell text-right font-mono text-slate-900 font-bold">
                                AED {{ number_format($p->retail_price, 2) }}
                            </td>

                            <!-- Margin -->
                            <td class="tbl-cell text-right font-mono">
                                <div class="font-bold text-emerald-600">+AED {{ number_format($margin, 2) }}</div>
                                <div class="text-[10px] text-slate-400 font-semibold">{{ $marginPct }}%</div>
                            </td>

                            <!-- Physical Stock -->
                            <td class="tbl-cell text-center font-mono font-black text-slate-900 {{ $isNegative ? 'text-rose-600 bg-rose-50 rounded-lg' : '' }}">
                                {{ $p->stock_quantity }}
                            </td>

                            <!-- Reserved -->
                            <td class="tbl-cell text-center font-mono text-amber-700 font-bold">
                                {{ $p->reserved_quantity > 0 ? $p->reserved_quantity : '—' }}
                            </td>

                            <!-- Available Stock -->
                            <td class="tbl-cell text-center font-mono font-black {{ $isOutOfStock ? 'text-rose-600' : ($isLowStock ? 'text-amber-600' : 'text-emerald-700') }}">
                                {{ $available }}
                            </td>

                            <!-- Reorder Level -->
                            <td class="tbl-cell text-center font-mono text-slate-500 text-xs">
                                {{ $p->minimum_stock_level }}
                            </td>

                            <!-- Status Badge -->
                            <td class="tbl-cell text-center">
                                @if($isNegative)
                                <span class="badge badge-rose animate-pulse">Negative</span>
                                @elseif($isOutOfStock)
                                <span class="badge badge-rose">Out of Stock</span>
                                @elseif($isLowStock)
                                <span class="badge badge-amber">Low Stock</span>
                                @else
                                <span class="badge badge-emerald">In Stock</span>
                                @endif
                            </td>

                            <!-- Quick Action Buttons -->
                            <td class="tbl-cell text-right space-x-1.5" onclick="event.stopPropagation();">
                                <button wire:click="openAdjustmentModal({{ $p->id }})" class="btn-outline py-1 px-2.5 text-xs text-slate-700 hover:bg-slate-100 rounded-lg">
                                    Adjust
                                </button>
                                <button wire:click="editProduct({{ $p->id }})" class="btn-outline py-1 px-2.5 text-xs text-emerald-700 border-emerald-300 hover:bg-emerald-50 rounded-lg">
                                    Edit
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="11" class="text-center py-12 text-slate-400">
                                <div class="space-y-1">
                                    <div class="font-bold text-sm text-slate-600">No matching products found</div>
                                    <div class="text-xs">Try adjusting search query or active filter settings.</div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex items-center gap-2 text-xs text-slate-500 font-semibold">
                    <span>Show:</span>
                    <select wire:model.live="perPage" class="bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1 text-xs font-bold text-slate-700 outline-none focus:border-emerald-500">
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                    <span>of {{ number_format($products->total()) }} products</span>
                </div>
                <div>
                    {{ $products->links() }}
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- FLOATING BULK ACTIONS TOOLBAR -->
    @if(count($selectedProductIds) > 0)
    <div class="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 bg-slate-900 text-white px-5 py-3 rounded-2xl shadow-2xl flex items-center gap-4 border border-slate-700 animate-in fade-in slide-in-from-bottom-4">
        <span class="text-xs font-bold text-slate-300">{{ count($selectedProductIds) }} selected</span>
        
        <button wire:click="bulkDelete" wire:confirm="Are you sure you want to delete selected products? Products with order or movement history will be safely archived to protect ledger history." class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition-colors">
            Delete Selected
        </button>

        <select wire:change="bulkCategory($event.target.value)" class="px-3 py-1.5 bg-slate-800 text-white border border-slate-700 rounded-xl text-xs font-bold outline-none">
            <option value="">Assign Category...</option>
            @foreach($categories as $c)
            <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
        </select>

        <select wire:change="bulkStatus($event.target.value)" class="px-3 py-1.5 bg-slate-800 text-white border border-slate-700 rounded-xl text-xs font-bold outline-none">
            <option value="">Set Status...</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>

        <button wire:click="$set('selectedProductIds', [])" class="text-xs text-slate-400 hover:text-white font-bold ml-2">
            Clear
        </button>
    </div>
    @endif

    <!-- TAB: CATEGORIES MANAGEMENT -->
    @if($activeTab === 'categories')
    <div class="space-y-4">
        <div class="flex items-center justify-between bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <div>
                <h3 class="font-bold text-slate-900 text-sm">Grocery Categories</h3>
                <p class="text-[11px] text-slate-500">Manage categories, upload thumbnails, and organize customer catalog groupings.</p>
            </div>
            <button wire:click="openCategoryModal()" class="btn-glow py-2 px-3.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-xs flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                + Add Category
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            @forelse($categories as $c)
            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 space-y-3 hover:shadow-md transition-shadow relative">
                <div class="flex items-center gap-3">
                    <div class="w-14 h-14 rounded-xl bg-slate-100 border border-slate-200 shrink-0 overflow-hidden flex items-center justify-center">
                        @if($c->image_url)
                        <img src="{{ $c->image_url }}" alt="{{ $c->name }}" class="w-full h-full object-cover">
                        @elseif($c->image)
                        <img src="{{ $c->image }}" alt="{{ $c->name }}" class="w-full h-full object-cover">
                        @else
                        <span class="text-slate-400 font-black text-sm">{{ substr($c->name, 0, 2) }}</span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="font-bold text-slate-900 text-sm truncate">{{ $c->name }}</h4>
                        <span class="text-[11px] font-mono text-slate-500 font-semibold">{{ $c->code ?: 'CAT-'.$c->id }}</span>
                        <div class="mt-1">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                {{ $c->products()->count() }} Products
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button wire:click="openCategoryModal({{ $c->id }})" class="px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-100 rounded-lg border border-slate-200">
                        Edit
                    </button>
                    <button wire:click="confirmDeleteCategory({{ $c->id }})" class="px-2.5 py-1 text-xs font-bold text-rose-600 hover:bg-rose-50 rounded-lg border border-rose-200">
                        Delete
                    </button>
                </div>
            </div>
            @empty
            <div class="col-span-full p-8 text-center text-slate-400 bg-white rounded-2xl border border-slate-200">
                No categories found. Click "+ Add Category" to create one.
            </div>
            @endforelse
        </div>
    </div>
    @endif

    <!-- TAB 2: STOCK MOVEMENTS LEDGER (APPEND-ONLY) -->
    @if($activeTab === 'movements')
    <div class="space-y-4">
        <div class="bg-white border border-slate-200/80 rounded-2xl overflow-hidden shadow-xs">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-900 text-sm">Append-Only Stock Movement Ledger</h3>
                    <p class="text-[11px] text-slate-500">Every stock mutation is permanently audited with previous and resulting stock counts.</p>
                </div>
                <span class="badge badge-slate">{{ $movements->total() }} total records</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="tbl-head bg-slate-50 border-b border-slate-200 font-bold uppercase tracking-wider text-[11px] text-slate-500">
                        <tr>
                            <th class="p-3.5">Date & Time</th>
                            <th class="p-3.5">Product</th>
                            <th class="p-3.5">Movement Type</th>
                            <th class="p-3.5 text-center">Change Qty</th>
                            <th class="p-3.5 text-center">Before &rarr; After</th>
                            <th class="p-3.5 text-right">Unit Cost</th>
                            <th class="p-3.5">Reference / Reason</th>
                            <th class="p-3.5 text-right">Staff / User</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium font-mono">
                        @forelse($movements as $m)
                        @php
                            $isPositive = $m->quantity > 0;
                        @endphp
                        <tr class="tbl-row hover:bg-slate-50/80">
                            <td class="tbl-cell text-slate-500 text-[11px]">
                                {{ $m->created_at->format('M d, Y H:i') }}
                            </td>

                            <td class="tbl-cell font-sans">
                                <div class="font-bold text-slate-900 text-xs">{{ $m->product?->name ?? 'Deleted Product' }}</div>
                                <div class="text-[10px] text-amber-700 font-mono">{{ $m->product?->barcode }}</div>
                            </td>

                            <td class="tbl-cell font-sans">
                                @if(in_array($m->type, ['Purchase', 'Stock Received']))
                                <span class="badge badge-emerald">{{ $m->type }}</span>
                                @elseif(in_array($m->type, ['Damage', 'Expiry']))
                                <span class="badge badge-rose">{{ $m->type }}</span>
                                @elseif(in_array($m->type, ['POS Sale', 'Online Order']))
                                <span class="badge badge-slate">{{ $m->type }}</span>
                                @elseif($m->type === 'Physical Count Correction')
                                <span class="badge badge-amber">{{ $m->type }}</span>
                                @else
                                <span class="badge badge-teal">{{ $m->type }}</span>
                                @endif
                            </td>

                            <td class="tbl-cell text-center font-bold text-sm {{ $isPositive ? 'text-emerald-600' : 'text-rose-600' }}">
                                {{ $isPositive ? '+' . $m->quantity : $m->quantity }}
                            </td>

                            <td class="tbl-cell text-center text-slate-600 font-bold">
                                {{ $m->stock_before }} &rarr; <span class="text-slate-900 font-black">{{ $m->stock_after }}</span>
                            </td>

                            <td class="tbl-cell text-right text-slate-900">
                                AED {{ number_format($m->unit_cost ?? 0, 2) }}
                            </td>

                            <td class="tbl-cell font-sans text-xs text-slate-600">
                                <div class="font-semibold text-slate-900">{{ $m->reference_type ? $m->reference_type . ' #' . $m->reference_id : 'Adjustment' }}</div>
                                <div class="text-[11px] text-slate-500">{{ $m->reason }}</div>
                            </td>

                            <td class="tbl-cell text-right font-sans text-xs text-slate-500 font-bold">
                                {{ $m->created_by ?? 'System' }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-10 text-slate-400 font-sans">No stock movements recorded yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-100">
                {{ $movements->links() }}
            </div>
        </div>
    </div>
    @endif

    <!-- TAB 3: PHYSICAL STOCK COUNTS -->
    @if($activeTab === 'counts')
    <div class="space-y-6">
        @if(!$activeCount)
        <!-- Stock Counts Sessions List -->
        <div class="bg-white border border-slate-200/80 rounded-2xl overflow-hidden shadow-xs">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-900 text-sm">Physical Inventory Count Sessions</h3>
                    <p class="text-[11px] text-slate-500">Perform periodic shelf counts to identify inventory shrinkage and automatically reconcile variances.</p>
                </div>
                <button wire:click="$set('showNewCountModal', true)" class="btn-glow text-xs py-2 px-3.5">
                    + Start New Count Session
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="tbl-head bg-slate-50 border-b border-slate-200 font-bold uppercase tracking-wider text-[11px] text-slate-500">
                        <tr>
                            <th class="p-3.5">Session No</th>
                            <th class="p-3.5">Category Scope</th>
                            <th class="p-3.5 text-center">Total Products</th>
                            <th class="p-3.5 text-center">Variance Count</th>
                            <th class="p-3.5 text-right">Value Impact (AED)</th>
                            <th class="p-3.5 text-center">Status</th>
                            <th class="p-3.5">Created By</th>
                            <th class="p-3.5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        @forelse($counts as $cnt)
                        <tr class="tbl-row hover:bg-slate-50/80">
                            <td class="tbl-cell font-mono font-bold text-slate-900">{{ $cnt->count_number }}</td>
                            <td class="tbl-cell font-bold text-slate-700">{{ $cnt->category?->name ?? 'All Store Inventory' }}</td>
                            <td class="tbl-cell text-center font-mono font-bold">{{ $cnt->total_items }}</td>
                            <td class="tbl-cell text-center font-mono font-bold {{ $cnt->variance_count > 0 ? 'text-rose-600' : 'text-slate-500' }}">
                                {{ $cnt->variance_count }} items
                            </td>
                            <td class="tbl-cell text-right font-mono font-bold {{ $cnt->variance_value < 0 ? 'text-rose-600' : ($cnt->variance_value > 0 ? 'text-emerald-600' : 'text-slate-500') }}">
                                AED {{ number_format($cnt->variance_value, 2) }}
                            </td>
                            <td class="tbl-cell text-center">
                                @if($cnt->status === 'approved')
                                <span class="badge badge-emerald">Approved</span>
                                @elseif($cnt->status === 'counting')
                                <span class="badge badge-amber animate-pulse">Counting in Progress</span>
                                @else
                                <span class="badge badge-slate">{{ ucfirst($cnt->status) }}</span>
                                @endif
                            </td>
                            <td class="tbl-cell text-xs text-slate-500">
                                {{ $cnt->created_by }} • {{ $cnt->created_at->format('M d, Y') }}
                            </td>
                            <td class="tbl-cell text-right">
                                <button wire:click="viewCountSession({{ $cnt->id }})" class="btn-glow py-1 px-3 text-xs">
                                    {{ $cnt->status === 'approved' ? 'View Details' : 'Continue Count' }}
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-10 text-slate-400">No stock count sessions recorded yet. Start one above!</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @else
        <!-- ACTIVE COUNT ENTRY INTERFACE -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 space-y-4 shadow-xs">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-4">
                <div>
                    <div class="flex items-center gap-3">
                        <button wire:click="closeCountSession" class="text-slate-400 hover:text-slate-600">&larr; Back to Sessions</button>
                        <h3 class="font-extrabold text-slate-900 text-lg">Stock Count: {{ $activeCount->count_number }}</h3>
                        <span class="badge {{ $activeCount->status === 'approved' ? 'badge-emerald' : 'badge-amber' }}">{{ strtoupper($activeCount->status) }}</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">Scope: {{ $activeCount->category?->name ?? 'All Store Inventory' }} • Total Products: {{ $activeCount->total_items }}</p>
                </div>

                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <div class="text-[11px] font-bold text-slate-500 uppercase">Variance Impact</div>
                        <div class="text-lg font-black font-mono {{ $activeCount->variance_value < 0 ? 'text-rose-600' : 'text-slate-900' }}">
                            AED {{ number_format($activeCount->variance_value, 2) }}
                        </div>
                    </div>

                    @if($activeCount->status !== 'approved')
                    <button wire:click="approveCountSession({{ $activeCount->id }})" wire:confirm="Are you sure you want to approve this physical count? This will update physical stock and log correction movements." class="btn-glow bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 px-5 rounded-xl text-xs shadow-xs">
                        Approve & Apply Adjustments
                    </button>
                    @endif
                </div>
            </div>

            <!-- Items Count Table -->
            <div class="overflow-x-auto rounded-xl border border-slate-100">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50 font-bold uppercase tracking-wider text-[11px] text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="p-3">Barcode & Product</th>
                            <th class="p-3 text-center">System Qty</th>
                            <th class="p-3 text-center">Physical Count</th>
                            <th class="p-3 text-center">Variance</th>
                            <th class="p-3 text-right">Unit Cost</th>
                            <th class="p-3 text-right">Value Impact</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        @foreach($activeCount->items as $item)
                        @php
                            $var = (int) $item->variance;
                        @endphp
                        <tr class="hover:bg-slate-50/50">
                            <td class="p-3">
                                <div class="font-bold text-slate-900 text-sm">{{ $item->product?->name }}</div>
                                <div class="text-[10px] text-amber-700 font-mono">{{ $item->product?->barcode }}</div>
                            </td>

                            <td class="p-3 text-center font-mono font-bold text-slate-600">
                                {{ $item->system_quantity }}
                            </td>

                            <td class="p-3 text-center">
                                @if($activeCount->status !== 'approved')
                                <input 
                                    type="number" 
                                    value="{{ $item->physical_quantity }}"
                                    wire:change="updateCountItemQuantity({{ $item->id }}, $event.target.value)"
                                    class="w-20 text-center font-mono font-bold text-sm bg-slate-50 border border-slate-300 rounded-lg p-1.5 focus:border-emerald-500"
                                >
                                @else
                                <span class="font-mono font-bold text-sm text-slate-900">{{ $item->physical_quantity }}</span>
                                @endif
                            </td>

                            <td class="p-3 text-center font-mono font-bold">
                                @if($var < 0)
                                <span class="text-rose-600 bg-rose-50 px-2 py-0.5 rounded-full">{{ $var }}</span>
                                @elseif($var > 0)
                                <span class="text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">+{{ $var }}</span>
                                @else
                                <span class="text-slate-400">0 (Match)</span>
                                @endif
                            </td>

                            <td class="p-3 text-right font-mono text-slate-600">
                                AED {{ number_format($item->unit_cost, 2) }}
                            </td>

                            <td class="p-3 text-right font-mono font-bold {{ $item->variance_value < 0 ? 'text-rose-600' : ($item->variance_value > 0 ? 'text-emerald-600' : 'text-slate-400') }}">
                                AED {{ number_format($item->variance_value, 2) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
    @endif

    <!-- TAB 4: REORDER RECOMMENDATIONS -->
    @if($activeTab === 'reorder')
    <div class="bg-white border border-slate-200/80 rounded-2xl overflow-hidden shadow-xs space-y-4 p-5">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-bold text-slate-900 text-sm">Automated Inventory Replenishment & Reorder Suggestions</h3>
                <p class="text-[11px] text-slate-500">Products currently at or below their reorder threshold, prioritized by stock urgency.</p>
            </div>
            <a href="/admin/receiving" class="btn-glow text-xs py-2 px-4">
                Launch Stock Receiving &rarr;
            </a>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-100">
            <table class="w-full text-left text-xs text-slate-700">
                <thead class="tbl-head bg-slate-50 border-b border-slate-200 font-bold uppercase tracking-wider text-[11px] text-slate-500">
                    <tr>
                        <th class="p-3.5">Product & Barcode</th>
                        <th class="p-3.5">Category</th>
                        <th class="p-3.5 text-center">Available Stock</th>
                        <th class="p-3.5 text-center">Reorder Level</th>
                        <th class="p-3.5 text-center">Suggested Order Qty</th>
                        <th class="p-3.5 text-right">Est. Replenishment Cost</th>
                        <th class="p-3.5">Primary Supplier</th>
                        <th class="p-3.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($reorderSuggestions as $item)
                    @php $prod = $item['product']; @endphp
                    <tr class="hover:bg-slate-50/80">
                        <td class="p-3.5">
                            <div class="font-bold text-slate-900 text-sm">{{ $prod->name }}</div>
                            <div class="text-[10px] text-amber-700 font-mono">{{ $prod->barcode }}</div>
                        </td>

                        <td class="p-3.5">
                            <span class="badge badge-amber">{{ $prod->category?->name ?? 'Uncategorized' }}</span>
                        </td>

                        <td class="p-3.5 text-center font-mono font-black text-rose-600 text-sm">
                            {{ $item['available'] }}
                        </td>

                        <td class="p-3.5 text-center font-mono font-bold text-slate-600">
                            {{ $item['reorder_level'] }}
                        </td>

                        <td class="p-3.5 text-center font-mono font-black text-emerald-600 text-base">
                            +{{ $item['suggested_quantity'] }} units
                        </td>

                        <td class="p-3.5 text-right font-mono font-bold text-slate-900">
                            AED {{ number_format($item['estimated_cost'], 2) }}
                        </td>

                        <td class="p-3.5 font-bold text-slate-700">
                            {{ $item['supplier'] }}
                        </td>

                        <td class="p-3.5 text-right">
                            <a href="/admin/receiving" class="btn-outline py-1 px-3 text-xs text-emerald-700 border-emerald-300 hover:bg-emerald-50 rounded-lg font-bold">
                                Receive
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-12 text-slate-400">
                            <div class="font-bold text-sm text-emerald-700">All products are healthy!</div>
                            <div class="text-xs text-slate-500">No items are currently below their reorder levels.</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- TAB 5: VALUATION & CATEGORY BREAKDOWN -->
    @if($activeTab === 'valuation')
    <div class="bg-white border border-slate-200/80 rounded-2xl p-5 space-y-4 shadow-xs">
        <div>
            <h3 class="font-bold text-slate-900 text-sm">Category Inventory Valuation & Potential Margins</h3>
            <p class="text-[11px] text-slate-500">Breakdown of capital tied up in stock versus expected retail gross yield by department.</p>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-100">
            <table class="w-full text-left text-xs text-slate-700">
                <thead class="tbl-head bg-slate-50 border-b border-slate-200 font-bold uppercase tracking-wider text-[11px] text-slate-500">
                    <tr>
                        <th class="p-3.5">Category</th>
                        <th class="p-3.5 text-center">Product Count</th>
                        <th class="p-3.5 text-center">Physical Units</th>
                        <th class="p-3.5 text-right">Total Cost Value</th>
                        <th class="p-3.5 text-right">Total Retail Value</th>
                        <th class="p-3.5 text-right">Potential Margin</th>
                        <th class="p-3.5 text-center">Margin %</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium font-mono">
                    @foreach($categoryValuations as $cv)
                    @php
                        $mPct = $cv['retail_value'] > 0 ? round(($cv['potential_margin'] / $cv['retail_value']) * 100, 1) : 0;
                    @endphp
                    <tr class="hover:bg-slate-50/80">
                        <td class="p-3.5 font-sans font-bold text-slate-900 text-sm">{{ $cv['name'] }}</td>
                        <td class="p-3.5 text-center text-slate-600 font-bold">{{ $cv['product_count'] }}</td>
                        <td class="p-3.5 text-center font-black text-slate-900">{{ number_format($cv['total_units']) }}</td>
                        <td class="p-3.5 text-right font-bold text-rose-600">AED {{ number_format($cv['cost_value'], 2) }}</td>
                        <td class="p-3.5 text-right font-bold text-slate-900">AED {{ number_format($cv['retail_value'], 2) }}</td>
                        <td class="p-3.5 text-right font-black text-emerald-600">+AED {{ number_format($cv['potential_margin'], 2) }}</td>
                        <td class="p-3.5 text-center">
                            <span class="badge badge-emerald font-bold">{{ $mPct }}%</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- ================================================================= -->
    <!-- SLIDE-OVER PRODUCT INVENTORY DETAIL DRAWER -->
    <!-- ================================================================= -->
    @if($drawerProduct)
    <div class="fixed inset-0 z-50 overflow-hidden" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity" wire:click="closeDrawer"></div>

        <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
            <div class="w-screen max-w-xl bg-white shadow-2xl flex flex-col justify-between">
                
                <!-- Drawer Header -->
                <div class="p-6 border-b border-slate-200/80 flex items-start justify-between bg-slate-50/50">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="badge badge-amber font-mono">{{ $drawerProduct->barcode }}</span>
                            @if($drawerProduct->stock_status === 'out_of_stock')
                            <span class="badge badge-rose">Out of Stock</span>
                            @elseif($drawerProduct->stock_status === 'low_stock')
                            <span class="badge badge-amber">Low Stock</span>
                            @else
                            <span class="badge badge-emerald">In Stock</span>
                            @endif
                        </div>
                        <h2 class="text-xl font-extrabold text-slate-900 mt-2">{{ $drawerProduct->name }}</h2>
                        <div class="text-xs text-slate-500 mt-0.5">{{ $drawerProduct->category?->name }} • {{ $drawerProduct->unit }}</div>
                    </div>
                    <button wire:click="closeDrawer" class="text-slate-400 hover:text-slate-600 text-2xl font-bold">&times;</button>
                </div>

                <!-- Drawer Body Scrollable Content -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6 text-xs">
                    
                    <!-- Stock Summary Cards -->
                    <div class="grid grid-cols-3 gap-3">
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-center space-y-0.5">
                            <div class="text-[10px] uppercase font-bold text-slate-500">Physical Stock</div>
                            <div class="text-xl font-black font-mono text-slate-900">{{ $drawerProduct->stock_quantity }}</div>
                        </div>

                        <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-center space-y-0.5">
                            <div class="text-[10px] uppercase font-bold text-amber-700">Reserved (Orders)</div>
                            <div class="text-xl font-black font-mono text-amber-700">{{ $drawerProduct->reserved_quantity }}</div>
                        </div>

                        <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-center space-y-0.5">
                            <div class="text-[10px] uppercase font-bold text-emerald-700">Available to Sell</div>
                            <div class="text-xl font-black font-mono text-emerald-700">{{ $drawerProduct->available_quantity }}</div>
                        </div>
                    </div>

                    <!-- Pricing & Margin Breakdown -->
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-2.5">
                        <div class="font-extrabold text-slate-900 text-xs uppercase tracking-wider">Financial Valuation</div>
                        <div class="grid grid-cols-3 gap-2 font-mono">
                            <div>
                                <div class="text-[10px] text-slate-500 font-bold">Wholesale Cost</div>
                                <div class="font-black text-rose-600 text-sm">AED {{ number_format($drawerProduct->wholesale_cost, 2) }}</div>
                            </div>
                            <div>
                                <div class="text-[10px] text-slate-500 font-bold">Retail Price</div>
                                <div class="font-black text-slate-900 text-sm">AED {{ number_format($drawerProduct->retail_price, 2) }}</div>
                            </div>
                            <div>
                                <div class="text-[10px] text-slate-500 font-bold">Unit Margin</div>
                                <div class="font-black text-emerald-600 text-sm">
                                    +AED {{ number_format($drawerProduct->retail_price - $drawerProduct->wholesale_cost, 2) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Inventory Properties & Parameters -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 bg-white border border-slate-200 rounded-xl space-y-1">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Reorder Threshold</div>
                            <div class="font-mono font-bold text-slate-800 text-sm">{{ $drawerProduct->minimum_stock_level }} units</div>
                        </div>

                        <div class="p-3 bg-white border border-slate-200 rounded-xl space-y-1">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Expiry Date</div>
                            <div class="font-mono font-bold text-slate-800 text-sm">
                                {{ $drawerProduct->expiry_date ? $drawerProduct->expiry_date->format('M d, Y') : 'N/A' }}
                            </div>
                        </div>
                    </div>

                    <!-- Product Movement Ledger Timeline -->
                    <div class="space-y-3 pt-2">
                        <div class="flex items-center justify-between">
                            <h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider">Recent Stock Movements</h4>
                            <span class="text-[11px] text-slate-400">Last 20 operations</span>
                        </div>

                        <div class="space-y-2 font-mono">
                            @forelse($drawerProduct->stockMovements as $mv)
                            <div class="p-3 bg-white border border-slate-100 rounded-xl flex items-center justify-between text-xs">
                                <div>
                                    <div class="font-sans font-bold text-slate-900 flex items-center gap-2">
                                        <span>{{ $mv->type }}</span>
                                        <span class="text-[10px] text-slate-400 font-normal">({{ $mv->created_at->format('M d, H:i') }})</span>
                                    </div>
                                    <div class="font-sans text-[11px] text-slate-500 mt-0.5">{{ $mv->reason }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="font-black text-sm {{ $mv->quantity > 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                        {{ $mv->quantity > 0 ? '+' . $mv->quantity : $mv->quantity }}
                                    </div>
                                    <div class="text-[10px] text-slate-400">{{ $mv->stock_before }} &rarr; {{ $mv->stock_after }}</div>
                                </div>
                            </div>
                            @empty
                            <div class="text-center py-6 text-slate-400">No stock movement recorded for this item.</div>
                            @endforelse
                        </div>
                    </div>

                </div>

                <!-- Drawer Footer Actions -->
                <div class="p-4 border-t border-slate-200/80 bg-slate-50 flex items-center justify-between gap-3">
                    <a href="/admin/receiving" class="btn-glow flex-1 justify-center py-2.5 text-xs text-center font-bold">
                        Receive Stock
                    </a>

                    <button wire:click="openAdjustmentModal({{ $drawerProduct->id }})" class="btn-outline flex-1 justify-center py-2.5 text-xs font-bold border border-slate-300">
                        Adjust Stock
                    </button>

                    <button wire:click="editProduct({{ $drawerProduct->id }})" class="btn-outline flex-1 justify-center py-2.5 text-xs font-bold text-emerald-700 border-emerald-300 bg-emerald-50">
                        Edit Product
                    </button>
                </div>

            </div>
        </div>
    </div>
    @endif

    <!-- ================================================================= -->
    <!-- STOCK ADJUSTMENT MODAL -->
    <!-- ================================================================= -->
    @if($showAdjustmentModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
        <div class="bg-white rounded-3xl p-6 w-full max-w-md shadow-2xl space-y-4 border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="font-black text-slate-900 text-base">Adjust Stock: {{ $adjustProductName }}</h3>
                <button wire:click="$set('showAdjustmentModal', false)" class="text-slate-400 hover:text-slate-600 font-bold text-xl">&times;</button>
            </div>

            <form wire:submit.prevent="saveAdjustment" class="space-y-3.5 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Adjustment Reason / Type</label>
                    <select wire:model="adjustType" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 font-bold text-slate-800">
                        <option value="Damage">Damage (Broken / Damaged Packaging)</option>
                        <option value="Expiry">Expiry (Past Best Before Date)</option>
                        <option value="Correction">Inventory Correction (Count Mismatch)</option>
                        <option value="Lost">Lost / Shrinkage</option>
                        <option value="Found">Found Unrecorded Stock</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-500 mb-1">Current Physical Stock</label>
                        <input type="text" value="{{ $adjustCurrentStock }}" disabled class="w-full bg-slate-100 border border-slate-200 rounded-xl p-2.5 font-mono font-bold text-slate-600 text-center">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">New Physical Stock</label>
                        <input type="number" wire:model="adjustNewQuantity" min="0" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 font-mono font-bold text-slate-900 text-center focus:border-emerald-500">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Mandatory Reason / Description</label>
                    <input type="text" wire:model="adjustReason" required placeholder="e.g. Dropped during restocking" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 font-medium focus:border-emerald-500">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Internal Audit Notes (Optional)</label>
                    <textarea wire:model="adjustNotes" rows="2" placeholder="Additional audit details..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 font-medium focus:border-emerald-500"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" wire:click="$set('showAdjustmentModal', false)" class="btn-outline py-2 px-4 text-xs font-bold">Cancel</button>
                    <button type="submit" class="btn-glow py-2 px-5 text-xs font-bold">Save Adjustment</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- ================================================================= -->
    <!-- START NEW STOCK COUNT MODAL -->
    <!-- ================================================================= -->
    @if($showNewCountModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
        <div class="bg-white rounded-3xl p-6 w-full max-w-md shadow-2xl space-y-4 border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="font-black text-slate-900 text-base">Initialize Stock Count Session</h3>
                <button wire:click="$set('showNewCountModal', false)" class="text-slate-400 hover:text-slate-600 font-bold text-xl">&times;</button>
            </div>

            <form wire:submit.prevent="startNewCount" class="space-y-3.5 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Category Scope</label>
                    <select wire:model="countCategoryId" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 font-bold text-slate-800">
                        <option value="">All Store Inventory (Full Count)</option>
                        @foreach($categories as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-[10px] text-slate-400 mt-1">Select a specific department or count the entire grocery store.</p>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Session Notes / Instructions</label>
                    <input type="text" wire:model="countNotes" placeholder="e.g. End of Month Shelf Audit" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 font-medium">
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" wire:click="$set('showNewCountModal', false)" class="btn-outline py-2 px-4 text-xs font-bold">Cancel</button>
                    <button type="submit" class="btn-glow py-2 px-5 text-xs font-bold">Start Counting</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- ================================================================= -->
    <!-- PRODUCT CREATE / EDIT MODAL -->
    <!-- ================================================================= -->
    @if($showProductModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
        <div class="bg-white rounded-3xl p-6 w-full max-w-lg shadow-2xl space-y-4 border border-slate-200 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="font-black text-slate-900 text-base">{{ $editingProductId ? 'Edit Product' : 'Add New Product' }}</h3>
                <button wire:click="$set('showProductModal', false)" class="text-slate-400 hover:text-slate-600 font-bold text-xl">&times;</button>
            </div>

            <form wire:submit.prevent="saveProduct" class="space-y-3.5 text-xs">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Barcode *</label>
                        <input type="text" wire:model="barcode" required placeholder="e.g. 8901234567890" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 font-mono font-bold focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">SKU (Optional)</label>
                        <input type="text" wire:model="sku" placeholder="e.g. MILK-1L" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 font-mono focus:border-emerald-500">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Product Name *</label>
                    <input type="text" wire:model="name" required placeholder="e.g. Al Rawabi Fresh Milk 1L" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 font-bold focus:border-emerald-500">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Category *</label>
                        <select wire:model="categoryId" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 font-medium">
                            @foreach($categories as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Unit / Pack</label>
                        <input type="text" wire:model="unit" placeholder="e.g. 1 Litre, 500g" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 font-medium">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Wholesale Cost (AED) *</label>
                        <input type="number" step="0.01" wire:model="wholesaleCost" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 font-mono font-bold text-rose-600 focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Retail Selling Price (AED) *</label>
                        <input type="number" step="0.01" wire:model="retailPrice" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 font-mono font-bold text-emerald-600 focus:border-emerald-500">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-2">
                    @if(!$editingProductId)
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Initial Stock *</label>
                        <input type="number" wire:model="stockQuantity" min="0" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 font-mono font-bold text-center">
                    </div>
                    @endif
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Reorder Level *</label>
                        <input type="number" wire:model="minimumStockLevel" min="0" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 font-mono font-bold text-center">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Max Capacity</label>
                        <input type="number" wire:model="maximumStockLevel" min="0" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 font-mono font-bold text-center">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Expiry Date (Optional)</label>
                        <input type="date" wire:model="expiryDate" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 font-mono">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Primary Supplier</label>
                        <input type="text" wire:model="supplierName" placeholder="e.g. Al Rawabi Dairy" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 font-medium">
                    </div>
                </div>

                <!-- Product Image Upload -->
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Product Image (Optional)</label>
                    <div class="flex items-center gap-3">
                        @if($productImage)
                        <img src="{{ $productImage->temporaryUrl() }}" class="w-12 h-12 rounded-xl object-cover border border-slate-200">
                        @elseif($currentImageUrl)
                        <img src="{{ $currentImageUrl }}" class="w-12 h-12 rounded-xl object-cover border border-slate-200">
                        @endif
                        <input type="file" wire:model="productImage" accept="image/*" class="text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                    </div>
                    <div wire:loading wire:target="productImage" class="text-[10px] text-emerald-600 font-bold mt-1">Uploading image preview...</div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" wire:click="$set('showProductModal', false)" class="btn-outline py-2 px-4 text-xs font-bold">Cancel</button>
                    <button type="submit" class="btn-glow py-2 px-5 text-xs font-bold">Save Product</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- EXCEL / CSV PRODUCTS IMPORT MODAL -->
    @if($showImportModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
        <div class="bg-white rounded-3xl p-6 w-full max-w-lg shadow-2xl space-y-4 border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <h3 class="font-black text-slate-900 text-base">Import Products via Excel / CSV</h3>
                </div>
                <button wire:click="$set('showImportModal', false)" class="text-slate-400 hover:text-slate-600 font-bold text-xl">&times;</button>
            </div>

            <!-- Template Download -->
            <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl flex items-center justify-between gap-4">
                <div>
                    <h4 class="font-bold text-emerald-950 text-xs">1. Download Official Template</h4>
                    <p class="text-[11px] text-emerald-800 mt-0.5">Includes columns for Name, SKU, Barcode, Category, Unit, Cost, and Retail Price.</p>
                </div>
                <a href="/api/v1/admin/products/import-template" download="Baqqala_Products_Import_Template.csv" class="btn-glow py-1.5 px-3 text-xs font-bold shrink-0">
                    Download
                </a>
            </div>

            <!-- File Upload Input -->
            <form wire:submit.prevent="processProductImport" class="space-y-4 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">2. Select Spreadsheet File (.csv, .xlsx)</label>
                    <input type="file" wire:model="importFile" required accept=".csv,.txt" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-100 file:text-slate-800 hover:file:bg-slate-200">
                    <div wire:loading wire:target="importFile" class="text-[10px] text-emerald-600 font-bold mt-1">Reading spreadsheet...</div>
                </div>

                <div class="flex items-center gap-2 p-3 bg-slate-50 border border-slate-200 rounded-xl">
                    <input type="checkbox" wire:model="importStockQuantities" id="importStockQuantities" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    <label for="importStockQuantities" class="font-bold text-slate-700 cursor-pointer">
                        Update physical stock quantities for existing SKUs
                    </label>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button type="button" wire:click="$set('showImportModal', false)" class="btn-outline py-2 px-4 text-xs font-bold">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" class="btn-glow py-2 px-5 text-xs font-bold">
                        <span wire:loading.remove wire:target="processProductImport">Execute Import</span>
                        <span wire:loading wire:target="processProductImport">Importing...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- CATEGORY CREATE / EDIT MODAL -->
    @if($showCategoryModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
        <div class="bg-white rounded-3xl p-6 w-full max-w-md shadow-2xl space-y-4 border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="font-black text-slate-900 text-base">{{ $editingCategoryId ? 'Edit Category' : 'New Category' }}</h3>
                <button wire:click="$set('showCategoryModal', false)" class="text-slate-400 hover:text-slate-600 font-bold text-xl">&times;</button>
            </div>

            <form wire:submit.prevent="saveCategory" class="space-y-3.5 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Category Name *</label>
                    <input type="text" wire:model="categoryName" required placeholder="e.g. Dairy & Eggs" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 font-bold focus:border-emerald-500">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Code / Prefix</label>
                    <input type="text" wire:model="categoryCode" placeholder="e.g. DRY" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 font-mono uppercase focus:border-emerald-500">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Thumbnail Image</label>
                    <div class="flex items-center gap-3">
                        @if($categoryImage)
                        <img src="{{ $categoryImage->temporaryUrl() }}" class="w-12 h-12 rounded-xl object-cover border border-slate-200">
                        @elseif($currentCategoryImageUrl)
                        <img src="{{ $currentCategoryImageUrl }}" class="w-12 h-12 rounded-xl object-cover border border-slate-200">
                        @endif
                        <input type="file" wire:model="categoryImage" accept="image/*" class="text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" wire:click="$set('showCategoryModal', false)" class="btn-outline py-2 px-4 text-xs font-bold">Cancel</button>
                    <button type="submit" class="btn-glow py-2 px-5 text-xs font-bold">Save Category</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- CATEGORY SAFE DELETE PROTECTION MODAL -->
    @if($showDeleteCategoryModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
        <div class="bg-white rounded-3xl p-6 w-full max-w-md shadow-2xl space-y-4 border border-rose-200">
            <div class="flex items-center gap-3 border-b border-slate-100 pb-3">
                <div class="w-10 h-10 rounded-2xl bg-rose-100 text-rose-700 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <div>
                    <h3 class="font-black text-slate-900 text-base">Category Safe Deletion</h3>
                    <p class="text-[11px] text-slate-500">This category has existing products assigned to it.</p>
                </div>
            </div>

            <div class="space-y-3 text-xs">
                <p class="text-slate-600 font-medium">To protect your catalog integrity, you can reassign existing products to another category, or safely deactivate them.</p>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Reassign products to:</label>
                    <select wire:model="reassignCategoryId" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 font-medium">
                        <option value="">Do not reassign (deactivate products)</option>
                        @foreach($categories->where('id', '!=', $deleteCategoryId) as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 text-xs font-bold">
                <button type="button" wire:click="$set('showDeleteCategoryModal', false)" class="btn-outline py-2 px-4">Cancel</button>
                <button type="button" wire:click="executeDeleteCategory" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl">Proceed</button>
            </div>
        </div>
    </div>
    @endif

</div>
