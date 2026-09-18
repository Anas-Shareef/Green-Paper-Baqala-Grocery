<div class="p-4 sm:p-6 space-y-6" x-data="{
    copied: false,
    copyText(txt) {
        navigator.clipboard.writeText(txt);
        this.copied = true;
        setTimeout(() => this.copied = false, 2500);
    }
}" @keydown.window.escape="$wire.closeDrawer(); $wire.closePickingModal(); $wire.closeCodModal(); $wire.closeCancelModal(); $wire.closeFailedModal()">

    <!-- TOP HEADER: Command Center Header -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-slate-200 pb-5">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight font-sans">
                    Orders Control Center
                </h1>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-200 uppercase tracking-wider">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                    Live Dispatch
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1 font-medium">
                Real-time grocery intake, WhatsApp verification, stock picking, driver dispatch & COD accountability.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
            <!-- View Mode Switcher -->
            <div class="bg-slate-100 p-1 rounded-xl border border-slate-200 flex items-center text-xs font-bold">
                <button wire:click="setViewMode('list')"
                        class="px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 {{ $viewMode === 'list' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800' }}">
                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    List Table
                </button>
                <button wire:click="setViewMode('board')"
                        class="px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 {{ $viewMode === 'board' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800' }}">
                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path></svg>
                    Fulfillment Board
                </button>
            </div>

            <!-- Refresh Button -->
            <button wire:click="$refresh" wire:loading.attr="disabled"
                    class="px-3 py-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-1.5"
                    title="Refresh orders">
                <svg wire:loading.class="animate-spin text-emerald-600" class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                <span wire:loading.remove>Refresh</span>
                <span wire:loading>Syncing...</span>
            </button>

            <!-- Export & Import Side by Side -->
            <button wire:click="exportCsv"
                    class="px-3 py-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-1.5">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Export
            </button>

            <button wire:click="$set('showImportModal', true)"
                    class="px-3 py-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-1.5">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                Import
            </button>

            <!-- POS Counter Shortcut -->
            <a href="/pos" class="btn-glow py-2 px-3.5 text-xs font-extrabold flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                Barcode POS Counter
            </a>
        </div>
    </div>

    <!-- FLASH BANNER ALERT -->
    @if(session()->has('message'))
    <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-900 rounded-2xl text-xs font-bold flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>{{ session('message') }}</span>
        </div>
        <button wire:click="$set('bannerMessage', null)" class="text-emerald-700 hover:text-emerald-900 text-sm font-black">&times;</button>
    </div>
    @endif

    <!-- OPERATIONAL KPI STRIP -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-3">
        <!-- KPI 1: New -->
        <div wire:click="setSmartFilter('needs_attention')" class="kpi-card cursor-pointer group {{ $smartFilter === 'needs_attention' ? 'ring-2 ring-emerald-500' : '' }}">
            <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 flex items-center justify-between">
                <span>New Intake</span>
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            </div>
            <div class="text-2xl font-black text-slate-900 font-mono mt-1 group-hover:text-emerald-600 transition-colors">
                {{ $kpis['new'] }}
            </div>
            <div class="text-[10px] text-slate-400 mt-1">Requires triage</div>
        </div>

        <!-- KPI 2: Awaiting Confirmation -->
        <div wire:click="setSmartFilter('awaiting_whatsapp')" class="kpi-card cursor-pointer group {{ $smartFilter === 'awaiting_whatsapp' ? 'ring-2 ring-emerald-500' : '' }}">
            <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 flex items-center justify-between">
                <span>WhatsApp Pending</span>
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
            </div>
            <div class="text-2xl font-black text-slate-900 font-mono mt-1 group-hover:text-emerald-600 transition-colors">
                {{ $kpis['awaiting'] }}
            </div>
            <div class="text-[10px] text-slate-400 mt-1">PWA incoming</div>
        </div>

        <!-- KPI 3: Preparing / Picking -->
        <div wire:click="setStatusTab('preparing')" class="kpi-card cursor-pointer group {{ $statusTab === 'preparing' ? 'ring-2 ring-indigo-500' : '' }}">
            <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 flex items-center justify-between">
                <span>Picking / Prep</span>
                <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
            </div>
            <div class="text-2xl font-black text-slate-900 font-mono mt-1 group-hover:text-indigo-600 transition-colors">
                {{ $kpis['preparing'] }}
            </div>
            <div class="text-[10px] text-slate-400 mt-1">Staff picking items</div>
        </div>

        <!-- KPI 4: Ready for Dispatch -->
        <div wire:click="setStatusTab('ready')" class="kpi-card cursor-pointer group {{ $statusTab === 'ready' ? 'ring-2 ring-emerald-500' : '' }}">
            <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 flex items-center justify-between">
                <span>Ready Packed</span>
                <span class="w-2 h-2 rounded-full bg-teal-500"></span>
            </div>
            <div class="text-2xl font-black text-slate-900 font-mono mt-1 group-hover:text-teal-600 transition-colors">
                {{ $kpis['ready'] }}
            </div>
            <div class="text-[10px] text-slate-400 mt-1">Awaiting driver</div>
        </div>

        <!-- KPI 5: Out for Delivery -->
        <div wire:click="setStatusTab('out_for_delivery')" class="kpi-card cursor-pointer group {{ $statusTab === 'out_for_delivery' ? 'ring-2 ring-purple-500' : '' }}">
            <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 flex items-center justify-between">
                <span>On Delivery</span>
                <span class="w-2 h-2 rounded-full bg-purple-500"></span>
            </div>
            <div class="text-2xl font-black text-slate-900 font-mono mt-1 group-hover:text-purple-600 transition-colors">
                {{ $kpis['out_for_delivery'] }}
            </div>
            <div class="text-[10px] text-slate-400 mt-1">In transit to villa</div>
        </div>

        <!-- KPI 6: COD Outstanding -->
        <div wire:click="setSmartFilter('cod_unpaid')" class="kpi-card cursor-pointer group {{ $smartFilter === 'cod_unpaid' ? 'ring-2 ring-amber-500' : '' }}">
            <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 flex items-center justify-between">
                <span>COD Pending</span>
                <span class="text-[9px] bg-amber-100 text-amber-800 font-bold px-1 rounded">CASH</span>
            </div>
            <div class="text-xl sm:text-2xl font-black text-amber-700 font-mono mt-1">
                AED {{ number_format($kpis['cod_outstanding'], 2) }}
            </div>
            <div class="text-[10px] text-slate-400 mt-1">Active drivers</div>
        </div>

        <!-- KPI 7: Late / At Risk -->
        <div wire:click="setSmartFilter('late')" class="kpi-card cursor-pointer group {{ $kpis['late'] > 0 ? 'bg-rose-50/50 border-rose-200' : '' }} {{ $smartFilter === 'late' ? 'ring-2 ring-rose-500' : '' }}">
            <div class="text-[10px] font-extrabold uppercase tracking-wider {{ $kpis['late'] > 0 ? 'text-rose-700' : 'text-slate-500' }} flex items-center justify-between">
                <span>Late / At Risk</span>
                @if($kpis['late'] > 0)
                <span class="w-2 h-2 rounded-full bg-rose-600 animate-pulse"></span>
                @else
                <span class="w-2 h-2 rounded-full bg-slate-300"></span>
                @endif
            </div>
            <div class="text-2xl font-black {{ $kpis['late'] > 0 ? 'text-rose-700' : 'text-slate-900' }} font-mono mt-1">
                {{ $kpis['late'] }}
            </div>
            <div class="text-[10px] text-slate-400 mt-1">&gt;45 min SLA</div>
        </div>
    </div>

    <!-- STATUS PIPELINE TABS & SMART FILTERS -->
    <div class="bg-white p-3.5 rounded-2xl border border-slate-200/80 shadow-xs space-y-3">
        <!-- Top Status Tabs -->
        <div class="flex items-center justify-between flex-wrap gap-2">
            <div class="flex flex-wrap items-center gap-1.5 text-xs font-bold">
                @php
                    $tabs = [
                        'active' => 'Active Orders',
                        'all' => 'All Orders',
                        'awaiting_whatsapp' => 'Awaiting WhatsApp',
                        'confirmed' => 'Confirmed',
                        'preparing' => 'Preparing',
                        'ready' => 'Ready for Delivery',
                        'out_for_delivery' => 'Out For Delivery',
                        'delivered' => 'Delivered',
                        'exceptions' => 'Exceptions',
                    ];
                @endphp
                @foreach($tabs as $key => $label)
                <button wire:click="setStatusTab('{{ $key }}')"
                        class="px-3 py-1.5 rounded-xl transition-all {{ $statusTab === $key ? 'bg-emerald-600 text-white font-black shadow-xs shadow-emerald-600/20' : 'bg-slate-100 text-slate-600 hover:text-slate-900 hover:bg-slate-200' }}">
                    {{ $label }}
                </button>
                @endforeach
            </div>

            @if($search || $smartFilter || $filterPayment || $filterSource || $filterDriver || $filterZone || $filterDate !== 'all' || $statusTab !== 'active')
            <button wire:click="clearFilters" class="text-xs font-bold text-rose-600 hover:text-rose-800 flex items-center gap-1 px-2 py-1 rounded-lg hover:bg-rose-50 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                Reset Filters
            </button>
            @endif
        </div>

        <!-- Smart Filter Quick Pills -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-[11px] font-bold text-slate-600 border-t border-slate-100 pt-2.5">
            <span class="text-[10px] uppercase font-black tracking-wider text-slate-400 shrink-0 mr-1">Quick Shortcuts:</span>
            
            <button wire:click="setSmartFilter('needs_attention')"
                    class="px-2.5 py-1 rounded-full border transition-all shrink-0 {{ $smartFilter === 'needs_attention' ? 'bg-emerald-100 text-emerald-900 border-emerald-300 font-black' : 'bg-slate-50 border-slate-200 hover:bg-slate-100' }}">
                ⚡ Needs Attention
            </button>
            <button wire:click="setSmartFilter('late')"
                    class="px-2.5 py-1 rounded-full border transition-all shrink-0 {{ $smartFilter === 'late' ? 'bg-rose-100 text-rose-900 border-rose-300 font-black' : 'bg-slate-50 border-slate-200 hover:bg-slate-100' }}">
                ⚠ Late Orders
            </button>
            <button wire:click="setSmartFilter('cod_unpaid')"
                    class="px-2.5 py-1 rounded-full border transition-all shrink-0 {{ $smartFilter === 'cod_unpaid' ? 'bg-amber-100 text-amber-900 border-amber-300 font-black' : 'bg-slate-50 border-slate-200 hover:bg-slate-100' }}">
                💵 COD Unpaid
            </button>
            <button wire:click="setSmartFilter('unassigned')"
                    class="px-2.5 py-1 rounded-full border transition-all shrink-0 {{ $smartFilter === 'unassigned' ? 'bg-purple-100 text-purple-900 border-purple-300 font-black' : 'bg-slate-50 border-slate-200 hover:bg-slate-100' }}">
                🛵 Unassigned Drivers
            </button>
            <button wire:click="setSmartFilter('ready_to_deliver')"
                    class="px-2.5 py-1 rounded-full border transition-all shrink-0 {{ $smartFilter === 'ready_to_deliver' ? 'bg-teal-100 text-teal-900 border-teal-300 font-black' : 'bg-slate-50 border-slate-200 hover:bg-slate-100' }}">
                📦 Ready to Deliver
            </button>
            <button wire:click="setSmartFilter('today')"
                    class="px-2.5 py-1 rounded-full border transition-all shrink-0 {{ $smartFilter === 'today' ? 'bg-blue-100 text-blue-900 border-blue-300 font-black' : 'bg-slate-50 border-slate-200 hover:bg-slate-100' }}">
                📅 Today's Orders
            </button>
        </div>
    </div>

    <!-- TOOLBAR: SEARCH & ADVANCED FILTERS -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
        <!-- Debounced Search -->
        <div class="relative flex-1">
            <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="Search Order #, customer name, phone, villa, zone, item..."
                   class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:outline-none focus:border-emerald-600 focus:bg-white transition-all font-mono">
            @if($search)
            <button wire:click="$set('search', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-sm font-bold">&times;</button>
            @endif
        </div>

        <!-- Filter Dropdowns Grid -->
        <div class="flex flex-wrap items-center gap-2 text-xs font-bold">
            <!-- Payment Filter -->
            <select wire:model.live="filterPayment" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 text-xs font-semibold focus:outline-none focus:border-emerald-600">
                <option value="">Payment: All</option>
                <option value="pending">COD Unpaid</option>
                <option value="paid">COD Paid</option>
            </select>

            <!-- Source Filter -->
            <select wire:model.live="filterSource" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 text-xs font-semibold focus:outline-none focus:border-emerald-600">
                <option value="">Source: All</option>
                <option value="PWA">PWA Storefront</option>
                <option value="POS">POS Counter</option>
                <option value="Admin">Admin Manual</option>
                <option value="WhatsApp">WhatsApp</option>
            </select>

            <!-- Driver Filter -->
            <select wire:model.live="filterDriver" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 text-xs font-semibold focus:outline-none focus:border-emerald-600">
                <option value="">Driver: All</option>
                <option value="unassigned">Unassigned</option>
                @foreach($deliveryDrivers as $driver)
                <option value="{{ $driver->id }}">{{ $driver->name }}</option>
                @endforeach
            </select>

            <!-- Date Range Filter -->
            <select wire:model.live="filterDate" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 text-xs font-semibold focus:outline-none focus:border-emerald-600">
                <option value="all">Date: All Time</option>
                <option value="today">Today</option>
                <option value="yesterday">Yesterday</option>
                <option value="last7">Last 7 Days</option>
                <option value="month">This Month</option>
            </select>

            <!-- Sorting -->
            <select wire:model.live="sort" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-700 text-xs font-semibold focus:outline-none focus:border-emerald-600 font-mono">
                <option value="urgent">⚡ Urgent / Attention First</option>
                <option value="newest">🕒 Newest First</option>
                <option value="oldest">⏳ Oldest First</option>
                <option value="highest_total">💰 Highest Amount</option>
                <option value="lowest_total">📉 Lowest Amount</option>
            </select>
        </div>
    </div>

    <!-- BULK ACTIONS FLOATING ACTION BAR -->
    @if(count($selectedOrders) > 0)
    <div class="p-3 bg-slate-900 text-white rounded-2xl flex flex-col sm:flex-row items-center justify-between gap-3 shadow-xl animate-fade-in text-xs font-bold">
        <div class="flex items-center gap-2">
            <span class="px-2.5 py-1 bg-emerald-600 text-white rounded-lg font-mono font-black text-xs">
                {{ count($selectedOrders) }}
            </span>
            <span>orders selected</span>
            <button wire:click="$set('selectedOrders', [])" class="text-slate-400 hover:text-white underline ml-2">Deselect all</button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <select wire:model="bulkActionChoice" class="px-3 py-1.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs font-semibold focus:outline-none">
                <option value="">Choose Bulk Action...</option>
                <option value="confirm">Confirm Orders (Reserve Stock)</option>
                <option value="prepare">Start Preparing / Picking</option>
                <option value="ready">Mark Ready for Delivery</option>
                <option value="assign_driver">Assign Delivery Driver...</option>
                <option value="delivery_sheet">Generate Delivery Run Sheet</option>
                <option value="cancel">Cancel Orders</option>
            </select>

            @if($bulkActionChoice === 'assign_driver')
            <select wire:model="bulkDriverId" class="px-3 py-1.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs font-semibold focus:outline-none">
                <option value="">Select Driver...</option>
                @foreach($deliveryDrivers as $d)
                <option value="{{ $d->id }}">{{ $d->name }}</option>
                @endforeach
            </select>
            @endif

            <button wire:click="executeBulkAction"
                    class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-extrabold transition-all shadow-sm">
                Apply to Selected
            </button>
        </div>
    </div>
    @endif

    <!-- MAIN ORDERS VIEW (VIEW A: TABLE OR VIEW B: KANBAN BOARD) -->
    @if($viewMode === 'list')
        <!-- VIEW A: DATA-DENSE ORDERS TABLE -->
        <div class="bg-white border border-slate-200/80 rounded-2xl overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-extrabold uppercase text-[10px] tracking-wider">
                        <tr>
                            <th class="p-3.5 w-10 text-center">
                                <input type="checkbox" wire:model.live="selectAll" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            </th>
                            <th class="p-3.5">Order ID & Seq</th>
                            <th class="p-3.5">Customer / Mobile</th>
                            <th class="p-3.5">Delivery Villa</th>
                            <th class="p-3.5 text-center">Items</th>
                            <th class="p-3.5 text-right">Total (AED)</th>
                            <th class="p-3.5">Payment</th>
                            <th class="p-3.5 text-center">Status</th>
                            <th class="p-3.5">Driver</th>
                            <th class="p-3.5">Timing & SLA</th>
                            <th class="p-3.5 text-right">Next Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        @forelse($orders as $o)
                        @php
                            $isLate = $o->isLate(45);
                            $nextAction = $o->getNextAction();
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition-colors {{ $isLate ? 'bg-rose-50/30' : '' }}">
                            <!-- Checkbox -->
                            <td class="p-3.5 text-center">
                                <input type="checkbox" wire:model.live="selectedOrders" value="{{ $o->id }}" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            </td>

                            <!-- Order No & Customer Sequence -->
                            <td class="p-3.5 font-mono">
                                <div class="flex items-center gap-1.5">
                                    <button wire:click="viewOrder({{ $o->id }})" class="font-black text-slate-900 hover:text-emerald-700 text-xs font-mono tracking-tight hover:underline">
                                        {{ $o->order_number }}
                                    </button>
                                </div>
                                <div class="mt-0.5">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-black bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        #{{ $o->customer_order_number }} Customer Order
                                    </span>
                                </div>
                            </td>

                            <!-- Customer & Phone -->
                            <td class="p-3.5">
                                <div class="font-extrabold text-slate-900 text-xs flex items-center gap-1.5">
                                    {{ $o->customer_name_snapshot ?: ($o->customer->name ?? 'Walk-in Cashier') }}
                                    @if($o->customer_order_number == 1)
                                    <span class="text-[9px] bg-blue-100 text-blue-800 font-black px-1.5 py-0.2 rounded-full uppercase">New</span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-slate-500 flex items-center gap-2 mt-0.5">
                                    <span>+{{ $o->customer_phone_snapshot ?: ($o->customer->phone ?? 'N/A') }}</span>
                                    @if($o->customer_phone_snapshot || $o->customer?->phone)
                                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $o->customer_phone_snapshot ?: $o->customer->phone) }}" target="_blank" class="text-emerald-600 hover:text-emerald-800" title="Open WhatsApp">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                                    </a>
                                    @endif
                                </div>
                            </td>

                            <!-- Address & Map Link -->
                            <td class="p-3.5">
                                <div class="font-extrabold text-slate-900 text-xs">
                                    {{ $o->customer_villa ? 'Villa ' . $o->customer_villa : 'POS Counter' }}
                                </div>
                                <div class="text-[11px] text-slate-500 truncate max-w-[180px]" title="{{ $o->customer_address }}">
                                    {{ $o->customer_address ?: 'Walk-in / In-store' }}
                                </div>
                                @if($o->customer_address)
                                <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($o->customer_address) }}" target="_blank" class="text-[10px] text-emerald-700 hover:underline font-bold inline-flex items-center gap-0.5 mt-0.5">
                                    📍 View Map
                                </a>
                                @endif
                            </td>

                            <!-- Items -->
                            <td class="p-3.5 text-center font-mono font-bold text-slate-800">
                                <span class="px-2 py-0.5 bg-slate-100 rounded-lg text-[11px]">
                                    {{ $o->items->count() }} items
                                </span>
                            </td>

                            <!-- Total AED -->
                            <td class="p-3.5 text-right font-mono font-black text-slate-900 text-sm">
                                AED {{ number_format($o->total_amount, 2) }}
                            </td>

                            <!-- Payment Method & Status -->
                            <td class="p-3.5">
                                @if($o->payment_status === 'paid')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200 uppercase">
                                    ✓ COD PAID
                                </span>
                                @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-black bg-amber-100 text-amber-900 border border-amber-200 uppercase">
                                    COD UNPAID
                                </span>
                                @endif
                                <div class="text-[10px] text-slate-400 mt-0.5 uppercase font-bold">{{ $o->order_source }}</div>
                            </td>

                            <!-- Status Badge -->
                            <td class="p-3.5 text-center">
                                @if($o->status === 'delivered')
                                <span class="badge badge-emerald">Delivered</span>
                                @elseif($o->status === 'out_for_delivery')
                                <span class="badge badge-blue">Out for Delivery</span>
                                @elseif($o->status === 'ready')
                                <span class="badge bg-teal-100 text-teal-800 border-teal-200">Ready</span>
                                @elseif($o->status === 'preparing')
                                <span class="badge bg-indigo-100 text-indigo-800 border-indigo-200">Preparing</span>
                                @elseif($o->status === 'confirmed' || $o->status === 'accepted')
                                <span class="badge badge-blue">Confirmed</span>
                                @elseif($o->status === 'awaiting_whatsapp')
                                <span class="badge badge-amber">Awaiting WhatsApp</span>
                                @elseif($o->status === 'cancelled')
                                <span class="badge badge-rose">Cancelled</span>
                                @elseif($o->status === 'failed_delivery')
                                <span class="badge badge-rose">Delivery Failed</span>
                                @elseif($o->status === 'expired')
                                <span class="badge badge-slate">Expired</span>
                                @else
                                <span class="badge badge-slate">{{ str_replace('_', ' ', $o->status) }}</span>
                                @endif
                            </td>

                            <!-- Driver -->
                            <td class="p-3.5">
                                @if($o->deliveryStaff)
                                <div class="font-bold text-slate-900 text-xs flex items-center gap-1">
                                    🛵 {{ $o->deliveryStaff->name }}
                                </div>
                                @else
                                <span class="text-[10px] font-extrabold text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-md">
                                    Unassigned
                                </span>
                                @endif
                            </td>

                            <!-- Timing & SLA -->
                            <td class="p-3.5 text-[11px] font-mono">
                                <div class="font-bold text-slate-700">
                                    {{ $o->created_at->diffForHumans(null, true, true) }} ago
                                </div>
                                @if($isLate)
                                <div class="text-[10px] text-rose-600 font-extrabold flex items-center gap-0.5 mt-0.5">
                                    <span>⚠ {{ $o->getElapsedMinutes() - 45 }}m late</span>
                                </div>
                                @else
                                <div class="text-[10px] text-slate-400 mt-0.5">
                                    Due in {{ max(0, 45 - $o->getElapsedMinutes()) }}m
                                </div>
                                @endif
                            </td>

                            <!-- Action Buttons -->
                            <td class="p-3.5 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <!-- Primary Next Action Button -->
                                    @if(!$o->isTerminal())
                                    <button wire:click="executeNextAction({{ $o->id }})"
                                            class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black transition-all shadow-xs shrink-0">
                                        {{ $nextAction['label'] }}
                                    </button>
                                    @endif

                                    <!-- Manage Button opens Drawer -->
                                    <button wire:click="viewOrder({{ $o->id }})"
                                            class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-extrabold transition-colors">
                                        Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="11" class="p-12 text-center text-slate-400 font-medium">
                                <div class="max-w-sm mx-auto space-y-2">
                                    <div class="text-3xl">🎉</div>
                                    <div class="font-extrabold text-slate-700 text-sm">No orders matching current filter</div>
                                    <p class="text-xs text-slate-400">All orders are caught up or try resetting your search and filter criteria.</p>
                                    <button wire:click="clearFilters" class="mt-2 px-3 py-1.5 bg-emerald-50 text-emerald-700 rounded-xl text-xs font-extrabold">Clear all filters</button>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Table Pagination -->
            <div class="p-4 border-t border-slate-100 flex items-center justify-between">
                <div class="text-xs text-slate-500 font-medium font-mono">
                    Showing {{ $orders->firstItem() ?? 0 }} to {{ $orders->lastItem() ?? 0 }} of {{ $orders->total() }} orders
                </div>
                <div>
                    {{ $orders->links() }}
                </div>
            </div>
        </div>

    @else
        <!-- VIEW B: FULFILLMENT KANBAN BOARD -->
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
            @php
                $columns = [
                    'awaiting' => [
                        'title' => 'Awaiting WhatsApp',
                        'badge' => 'bg-amber-100 text-amber-800',
                        'orders' => $boardOrders['awaiting'] ?? collect(),
                    ],
                    'confirmed' => [
                        'title' => 'Confirmed',
                        'badge' => 'bg-blue-100 text-blue-800',
                        'orders' => $boardOrders['confirmed'] ?? collect(),
                    ],
                    'preparing' => [
                        'title' => 'Preparing / Picking',
                        'badge' => 'bg-indigo-100 text-indigo-800',
                        'orders' => $boardOrders['preparing'] ?? collect(),
                    ],
                    'ready' => [
                        'title' => 'Ready for Dispatch',
                        'badge' => 'bg-teal-100 text-teal-800',
                        'orders' => $boardOrders['ready'] ?? collect(),
                    ],
                    'out_for_delivery' => [
                        'title' => 'Out for Delivery',
                        'badge' => 'bg-purple-100 text-purple-800',
                        'orders' => $boardOrders['out_for_delivery'] ?? collect(),
                    ],
                ];
            @endphp

            @foreach($columns as $colKey => $colData)
            <div class="bg-slate-100/80 rounded-2xl p-3 border border-slate-200 space-y-3 flex flex-col max-h-[85vh]">
                <!-- Column Header -->
                <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                    <span class="font-extrabold text-xs text-slate-900">{{ $colData['title'] }}</span>
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-mono font-black {{ $colData['badge'] }}">
                        {{ $colData['orders']->count() }}
                    </span>
                </div>

                <!-- Cards Container -->
                <div class="space-y-2.5 overflow-y-auto pr-1 flex-1">
                    @forelse($colData['orders'] as $bo)
                    @php
                        $bLate = $bo->isLate(45);
                    @endphp
                    <div wire:click="viewOrder({{ $bo->id }})"
                         class="bg-white p-3.5 rounded-xl border {{ $bLate ? 'border-rose-300 bg-rose-50/20' : 'border-slate-200' }} shadow-xs hover:shadow-md transition-all cursor-pointer space-y-2 group">
                        
                        <!-- Top Row: Order ID & Age -->
                        <div class="flex items-center justify-between font-mono text-xs">
                            <span class="font-black text-slate-900 group-hover:text-emerald-700 transition-colors">
                                {{ $bo->order_number }}
                            </span>
                            <span class="text-[10px] {{ $bLate ? 'text-rose-600 font-black' : 'text-slate-400' }}">
                                {{ $bo->created_at->diffForHumans(null, true, true) }}
                            </span>
                        </div>

                        <!-- Customer & Villa -->
                        <div>
                            <div class="font-extrabold text-xs text-slate-900 truncate">
                                {{ $bo->customer_name_snapshot ?: ($bo->customer->name ?? 'Walk-in') }}
                            </div>
                            <div class="text-[11px] text-emerald-700 font-bold mt-0.5">
                                {{ $bo->customer_villa ? 'Villa ' . $bo->customer_villa : 'Store Counter' }}
                            </div>
                        </div>

                        <!-- Total & Items -->
                        <div class="flex items-center justify-between pt-1 border-t border-slate-100 text-xs">
                            <span class="font-mono font-bold text-slate-500 text-[11px]">
                                {{ $bo->items->count() }} items
                            </span>
                            <span class="font-mono font-black text-slate-900">
                                AED {{ number_format($bo->total_amount, 2) }}
                            </span>
                        </div>

                        <!-- Driver Info if assigned -->
                        @if($bo->deliveryStaff)
                        <div class="text-[10px] font-bold text-slate-600 flex items-center gap-1 bg-slate-50 p-1.5 rounded-lg">
                            🛵 {{ $bo->deliveryStaff->name }}
                        </div>
                        @endif

                        <!-- Action Button -->
                        <div class="pt-1" @click.stop>
                            <button wire:click="executeNextAction({{ $bo->id }})"
                                    class="w-full py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-black transition-all shadow-xs">
                                {{ $bo->getNextAction()['label'] }}
                            </button>
                        </div>
                    </div>
                    @empty
                    <div class="p-6 text-center text-slate-400 text-xs font-medium italic">
                        No orders in this stage
                    </div>
                    @endforelse
                </div>
            </div>
            @endforeach
        </div>
    @endif


    <!-- ========================================================================= -->
    <!-- SLIDE-OUT ORDER DETAIL DRAWER (DESKTOP & MOBILE RESPONSIVE)               -->
    <!-- ========================================================================= -->
    @if($showDrawer && $viewingOrder)
    <div class="fixed inset-0 z-50 overflow-hidden" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
        <!-- Backdrop Blur -->
        <div wire:click="closeDrawer" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity"></div>

        <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
            <div class="w-screen max-w-2xl bg-white shadow-2xl flex flex-col border-l border-slate-200">
                
                <!-- Drawer Header -->
                <div class="p-5 border-b border-slate-200 bg-slate-50/50 flex items-center justify-between">
                    <div class="space-y-0.5">
                        <div class="flex items-center gap-2 font-mono">
                            <h2 class="text-xl font-black text-slate-900 tracking-tight">
                                {{ $viewingOrder->order_number }}
                            </h2>
                            <span class="px-2 py-0.5 rounded text-xs font-black bg-emerald-100 text-emerald-800 border border-emerald-200">
                                #{{ $viewingOrder->customer_order_number }} Customer Order
                            </span>
                        </div>
                        <p class="text-xs text-slate-500">
                            Placed {{ $viewingOrder->created_at->format('d M Y, H:i') }} via {{ $viewingOrder->order_source }}
                        </p>
                    </div>

                    <!-- Navigation & Close Buttons -->
                    <div class="flex items-center gap-1.5">
                        <button wire:click="navigateOrder('prev')" @if(!$prevOrderId) disabled @endif
                                class="p-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-30 disabled:cursor-not-allowed"
                                title="Previous order">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        </button>
                        <button wire:click="navigateOrder('next')" @if(!$nextOrderId) disabled @endif
                                class="p-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-30 disabled:cursor-not-allowed"
                                title="Next order">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </button>
                        <button wire:click="closeDrawer" class="p-2 rounded-xl text-slate-400 hover:text-slate-700 text-xl font-bold ml-2">
                            &times;
                        </button>
                    </div>
                </div>

                <!-- Drawer Content (Scrollable) -->
                <div class="flex-1 overflow-y-auto p-5 space-y-5 text-xs text-slate-700">

                    <!-- Status Hero Banner -->
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] uppercase font-black tracking-wider text-slate-400">Order Lifecycle State</span>
                            <div class="text-base font-black text-slate-900 mt-0.5 flex items-center gap-2">
                                <span class="capitalize">{{ str_replace('_', ' ', $viewingOrder->status) }}</span>
                                @if($viewingOrder->isLate(45))
                                <span class="text-[10px] bg-rose-100 text-rose-800 font-extrabold px-2 py-0.5 rounded-full">
                                    ⚠ SLA Exceeded
                                </span>
                                @endif
                            </div>
                        </div>

                        <!-- 1-Click Next Action Primary Button -->
                        @if(!$viewingOrder->isTerminal())
                        <button wire:click="executeNextAction({{ $viewingOrder->id }})"
                                class="btn-glow py-2.5 px-5 text-xs font-black">
                            {{ $viewingOrder->getNextAction()['label'] }}
                        </button>
                        @endif
                    </div>

                    <!-- Customer & Delivery Address Card -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Customer Info -->
                        <div class="bg-white p-4 rounded-2xl border border-slate-200 space-y-2">
                            <div class="font-extrabold uppercase text-[10px] tracking-wider text-slate-400 flex items-center justify-between">
                                <span>Customer Profile</span>
                                <span class="text-emerald-700 font-bold">#{{ $viewingOrder->customer_order_number }} order</span>
                            </div>
                            <div class="text-sm font-black text-slate-900">
                                {{ $viewingOrder->customer_name_snapshot ?: ($viewingOrder->customer->name ?? 'Walk-in Customer') }}
                            </div>
                            <div class="text-slate-600 font-mono">
                                Phone: +{{ $viewingOrder->customer_phone_snapshot ?: ($viewingOrder->customer->phone ?? 'N/A') }}
                            </div>
                            
                            <!-- Quick Call / WhatsApp / Map Actions -->
                            <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
                                @if($viewingOrder->customer_phone_snapshot || $viewingOrder->customer?->phone)
                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $viewingOrder->customer_phone_snapshot ?: $viewingOrder->customer->phone) }}" target="_blank"
                                   class="px-2.5 py-1.5 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-lg font-bold text-[11px] flex items-center gap-1 hover:bg-emerald-100 transition-colors">
                                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                                    WhatsApp
                                </a>
                                <a href="tel:+{{ $viewingOrder->customer_phone_snapshot ?: $viewingOrder->customer->phone }}"
                                   class="px-2.5 py-1.5 bg-slate-100 text-slate-800 border border-slate-200 rounded-lg font-bold text-[11px] flex items-center gap-1 hover:bg-slate-200 transition-colors">
                                    📞 Call
                                </a>
                                @endif
                            </div>
                        </div>

                        <!-- Delivery Address -->
                        <div class="bg-white p-4 rounded-2xl border border-slate-200 space-y-2">
                            <div class="font-extrabold uppercase text-[10px] tracking-wider text-slate-400 flex items-center justify-between">
                                <span>Delivery Destination</span>
                                <span class="text-emerald-700 font-bold font-mono">Villa {{ $viewingOrder->customer_villa ?: 'Counter' }}</span>
                            </div>
                            <div class="text-xs font-semibold text-slate-800">
                                {{ $viewingOrder->customer_address ?: 'In-store / Pick up' }}
                            </div>
                            @if($viewingOrder->customer_notes_snapshot || $viewingOrder->notes)
                            <div class="text-[11px] text-amber-800 bg-amber-50 p-2 rounded-lg border border-amber-200">
                                <strong>Note:</strong> {{ $viewingOrder->customer_notes_snapshot ?: $viewingOrder->notes }}
                            </div>
                            @endif

                            @if($viewingOrder->customer_address)
                            <div class="pt-2 border-t border-slate-100">
                                <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($viewingOrder->customer_address) }}" target="_blank"
                                   class="text-[11px] text-emerald-700 font-extrabold hover:underline flex items-center gap-1">
                                    📍 Open Google Maps Navigation
                                </a>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Driver Assignment & COD Quick Controls -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Driver Assignment -->
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2">
                            <div class="font-extrabold uppercase text-[10px] tracking-wider text-slate-500">
                                Delivery Staff Assignment
                            </div>
                            <select wire:change="assignDriver({{ $viewingOrder->id }}, $event.target.value)"
                                    class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:border-emerald-600">
                                <option value="">Select Delivery Driver...</option>
                                @foreach($deliveryDrivers as $dd)
                                <option value="{{ $dd->id }}" {{ $viewingOrder->delivery_staff_id == $dd->id ? 'selected' : '' }}>
                                    🛵 {{ $dd->name }} ({{ strtoupper($dd->role) }})
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Cash on Delivery (COD) Status -->
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2">
                            <div class="font-extrabold uppercase text-[10px] tracking-wider text-slate-500 flex items-center justify-between">
                                <span>Cash Collection</span>
                                <span class="font-mono font-bold text-slate-900">AED {{ number_format($viewingOrder->total_amount, 2) }}</span>
                            </div>
                            @if($viewingOrder->payment_status === 'paid')
                            <div class="p-2 bg-emerald-100 border border-emerald-200 text-emerald-900 font-extrabold rounded-xl text-center text-xs">
                                ✓ COD Collected & Paid
                            </div>
                            @else
                            <button wire:click="openCodModal({{ $viewingOrder->id }})"
                                    class="w-full py-2 bg-amber-500 hover:bg-amber-600 text-white font-extrabold rounded-xl text-xs transition-colors shadow-xs">
                                Record Cash Payment Collected
                            </button>
                            @endif
                        </div>
                    </div>

                    <!-- Ordered Items Breakdown with Picking Progress -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <h3 class="font-extrabold uppercase text-[10px] tracking-wider text-slate-400">
                                Order Items Breakdown ({{ $viewingOrder->items->count() }} products)
                            </h3>
                            <button wire:click="openPickingModal({{ $viewingOrder->id }})"
                                    class="px-3 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-lg text-xs font-extrabold transition-colors">
                                📦 Open Picking Mode
                            </button>
                        </div>

                        <div class="bg-white rounded-2xl overflow-hidden border border-slate-200 shadow-xs">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-slate-500 text-[10px] font-extrabold uppercase tracking-wider border-b border-slate-200">
                                    <tr>
                                        <th class="p-3">Item</th>
                                        <th class="p-3 text-center">Picking</th>
                                        <th class="p-3 text-right">Qty</th>
                                        <th class="p-3 text-right">Unit Price</th>
                                        <th class="p-3 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($viewingOrder->items as $item)
                                    <tr>
                                        <td class="p-3 font-bold text-slate-900">
                                            {{ $item->product_name }}
                                        </td>
                                        <td class="p-3 text-center font-mono">
                                            @if($item->picked_quantity >= $item->quantity)
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800">
                                                ✓ {{ $item->picked_quantity }}/{{ $item->quantity }} Picked
                                            </span>
                                            @else
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-slate-100 text-slate-600">
                                                {{ $item->picked_quantity }}/{{ $item->quantity }}
                                            </span>
                                            @endif
                                        </td>
                                        <td class="p-3 text-right font-mono font-bold text-slate-700">
                                            ×{{ $item->quantity }}
                                        </td>
                                        <td class="p-3 text-right font-mono text-slate-600">
                                            AED {{ number_format($item->unit_price, 2) }}
                                        </td>
                                        <td class="p-3 text-right font-mono font-black text-slate-900">
                                            AED {{ number_format($item->total, 2) }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Financial Summary Formula -->
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-1.5 font-mono text-xs">
                        <div class="flex justify-between text-slate-600">
                            <span>Subtotal:</span>
                            <span>AED {{ number_format($viewingOrder->subtotal, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <span>Delivery Fee:</span>
                            <span class="text-emerald-700 font-bold">FREE (Villa Delivery)</span>
                        </div>
                        <div class="flex justify-between font-black text-slate-900 text-sm pt-2 border-t border-slate-200">
                            <span>TOTAL AMOUNT DUE:</span>
                            <span class="text-emerald-700">AED {{ number_format($viewingOrder->total_amount, 2) }}</span>
                        </div>
                    </div>

                    <!-- Chronological Activity Timeline Audit Trail -->
                    <div class="space-y-3 pt-2">
                        <h3 class="font-extrabold uppercase text-[10px] tracking-wider text-slate-400">
                            Chronological Activity Timeline & Audit Log
                        </h3>
                        
                        <div class="relative border-l-2 border-slate-200 ml-3 space-y-4">
                            @forelse($viewingOrder->activities as $act)
                            <div class="relative pl-5">
                                <div class="absolute -left-[7px] top-1.5 w-3 h-3 rounded-full bg-emerald-600 border-2 border-white shadow-xs"></div>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-slate-900">{{ $act->description }}</span>
                                    <span class="text-[10px] text-slate-400 font-mono">{{ $act->created_at->format('d M H:i') }}</span>
                                </div>
                                <div class="text-[11px] text-slate-500 mt-0.5">
                                    By <strong class="text-slate-700">{{ $act->actor_name ?: 'System' }}</strong>
                                </div>
                            </div>
                            @empty
                            <div class="pl-5 text-slate-400 text-xs italic">
                                Order created on {{ $viewingOrder->created_at->format('d M Y, H:i') }}
                            </div>
                            @endforelse
                        </div>
                    </div>

                </div>

                <!-- Drawer Action Footer -->
                <div class="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('invoice.download', ['order' => $viewingOrder->order_number]) }}" target="_blank"
                           class="px-3 py-2 bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 rounded-xl font-bold text-xs flex items-center gap-1.5 transition-colors">
                            📄 Download PDF
                        </a>
                        @if(!$viewingOrder->isTerminal())
                        <button wire:click="openCancelModal({{ $viewingOrder->id }})"
                                class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-xl font-bold text-xs transition-colors">
                            Cancel Order
                        </button>
                        <button wire:click="openFailedModal({{ $viewingOrder->id }})"
                                class="px-3 py-2 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 rounded-xl font-bold text-xs transition-colors">
                            Failed Delivery
                        </button>
                        @endif
                    </div>

                    <button wire:click="closeDrawer" class="btn-outline text-xs">
                        Close
                    </button>
                </div>

            </div>
        </div>
    </div>
    @endif


    <!-- ========================================================================= -->
    <!-- FOCUSED GROCERY PICKING MODAL                                             -->
    <!-- ========================================================================= -->
    @if($showPickingModal && $viewingOrder)
    <div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl border border-slate-200 max-w-xl w-full p-6 shadow-2xl space-y-5">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <span class="text-[10px] font-black uppercase text-indigo-600 tracking-wider">Focused Grocery Picking</span>
                    <h3 class="text-xl font-black text-slate-900 font-mono">{{ $viewingOrder->order_number }}</h3>
                </div>
                <button wire:click="closePickingModal" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <!-- Barcode Scanner Input -->
            <div class="space-y-1">
                <label class="text-xs font-bold text-slate-700">Scan Product Barcode or SKU:</label>
                <div class="flex items-center gap-2">
                    <input type="text"
                           wire:model="barcodeInput"
                           wire:keydown.enter="scanBarcode"
                           placeholder="Point handheld scanner or enter barcode / SKU..."
                           class="flex-1 px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono font-bold text-slate-900 focus:outline-none focus:border-indigo-600 focus:bg-white"
                           autofocus>
                    <button wire:click="scanBarcode" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs shadow-xs">
                        Scan Item
                    </button>
                </div>
                @if($pickingSuccessMsg)
                <div class="text-emerald-700 font-bold text-xs mt-1">{{ $pickingSuccessMsg }}</div>
                @endif
                @if($pickingErrorMsg)
                <div class="text-rose-600 font-bold text-xs mt-1">{{ $pickingErrorMsg }}</div>
                @endif
            </div>

            <!-- Items Checklist -->
            <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                @php
                    $totalRequired = $viewingOrder->items->sum('quantity');
                    $totalPicked = $viewingOrder->items->sum('picked_quantity');
                @endphp

                <div class="flex items-center justify-between text-xs font-bold text-slate-600 pb-1">
                    <span>Item Checklist</span>
                    <span class="font-mono text-indigo-700">{{ $totalPicked }} / {{ $totalRequired }} units picked</span>
                </div>

                <div class="space-y-1.5">
                    @foreach($viewingOrder->items as $item)
                    @php $isDone = $item->picked_quantity >= $item->quantity; @endphp
                    <div wire:click="toggleItemPicked({{ $item->id }})"
                         class="p-3 rounded-xl border {{ $isDone ? 'bg-emerald-50/60 border-emerald-200' : 'bg-slate-50 border-slate-200' }} flex items-center justify-between cursor-pointer transition-colors">
                        <div class="flex items-center gap-3">
                            <input type="checkbox" {{ $isDone ? 'checked' : '' }} class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 pointer-events-none">
                            <div>
                                <div class="font-extrabold text-xs {{ $isDone ? 'text-emerald-900 line-through' : 'text-slate-900' }}">
                                    {{ $item->product_name }}
                                </div>
                                <div class="text-[10px] text-slate-400 font-mono">
                                    SKU: {{ $item->product?->sku ?? 'N/A' }}
                                </div>
                            </div>
                        </div>

                        <div class="text-right font-mono">
                            <span class="text-xs font-black {{ $isDone ? 'text-emerald-800' : 'text-slate-800' }}">
                                {{ $item->picked_quantity }} / {{ $item->quantity }}
                            </span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Action Footer -->
            <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                <button wire:click="closePickingModal" class="btn-outline text-xs">
                    Pause / Close
                </button>
                <button wire:click="completePicking"
                        class="btn-glow py-2.5 px-5 text-xs font-black">
                    ✓ Complete Picking & Mark Ready
                </button>
            </div>

        </div>
    </div>
    @endif


    <!-- ========================================================================= -->
    <!-- COD PAYMENT COLLECTION MODAL                                              -->
    <!-- ========================================================================= -->
    @if($showCodModal)
    <div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl border border-slate-200 max-w-md w-full p-6 shadow-2xl space-y-4">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-black text-slate-900">Confirm Cash on Delivery Collection</h3>
                <button wire:click="closeCodModal" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="font-bold text-slate-700">Cash Amount Collected (AED):</label>
                    <input type="number" step="0.50"
                           wire:model="codAmount"
                           class="w-full mt-1 px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl font-mono font-black text-slate-900 text-base focus:outline-none focus:border-emerald-600">
                </div>

                <div>
                    <label class="font-bold text-slate-700">Reason for Difference (if not exact):</label>
                    <input type="text"
                           wire:model="codDiffReason"
                           placeholder="e.g. Customer tipped AED 2, or exact change shortage..."
                           class="w-full mt-1 px-4 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-emerald-600">
                    @error('codDiffReason') <span class="text-rose-600 text-[11px] font-bold">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button wire:click="closeCodModal" class="btn-outline text-xs">Cancel</button>
                <button wire:click="confirmCodCollection" class="btn-glow py-2 px-4 text-xs font-black">
                    ✓ Confirm Payment Collected
                </button>
            </div>

        </div>
    </div>
    @endif


    <!-- ========================================================================= -->
    <!-- CANCELLATION MODAL                                                        -->
    <!-- ========================================================================= -->
    @if($showCancelModal)
    <div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl border border-slate-200 max-w-md w-full p-6 shadow-2xl space-y-4">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-black text-rose-700">Cancel Order & Release Stock</h3>
                <button wire:click="closeCancelModal" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
            </div>

            <p class="text-xs text-slate-500">
                Cancelling this order will release all reserved grocery inventory back to available stock.
            </p>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="font-bold text-slate-700">Select Cancellation Reason:</label>
                    <select wire:model="cancelReason" class="w-full mt-1 px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-900">
                        <option value="Customer cancelled">Customer cancelled</option>
                        <option value="Out of stock shortage">Out of stock shortage</option>
                        <option value="Duplicate order placed">Duplicate order placed</option>
                        <option value="Unable to contact customer on WhatsApp">Unable to contact customer on WhatsApp</option>
                        <option value="Wrong address / Outside delivery zone">Wrong address / Outside delivery zone</option>
                        <option value="Other">Other (Type custom reason below)</option>
                    </select>
                </div>

                @if($cancelReason === 'Other')
                <div>
                    <label class="font-bold text-slate-700">Custom Reason Description:</label>
                    <input type="text" wire:model="cancelCustomReason" placeholder="Specify why this order was cancelled..." class="w-full mt-1 px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900">
                </div>
                @endif
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button wire:click="closeCancelModal" class="btn-outline text-xs">Close</button>
                <button wire:click="confirmCancelOrder" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-black transition-colors">
                    Confirm Cancellation
                </button>
            </div>

        </div>
    </div>
    @endif


    <!-- ========================================================================= -->
    <!-- FAILED DELIVERY MODAL                                                     -->
    <!-- ========================================================================= -->
    @if($showFailedModal)
    <div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl border border-slate-200 max-w-md w-full p-6 shadow-2xl space-y-4">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-black text-amber-800">Record Failed Delivery Attempt</h3>
                <button wire:click="closeFailedModal" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="font-bold text-slate-700">Failure Reason:</label>
                    <select wire:model="failedReason" class="w-full mt-1 px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-900">
                        <option value="Customer unavailable at villa">Customer unavailable at villa</option>
                        <option value="Phone unreachable / Not answering">Phone unreachable / Not answering</option>
                        <option value="Wrong villa address">Wrong villa address</option>
                        <option value="Customer refused delivery">Customer refused delivery</option>
                        <option value="No cash for COD payment">No cash for COD payment</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div>
                    <label class="font-bold text-slate-700">Driver Notes / Follow-up:</label>
                    <textarea wire:model="failedNotes" placeholder="e.g. Customer requested retry delivery at 8 PM..." class="w-full mt-1 px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 h-20"></textarea>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button wire:click="closeFailedModal" class="btn-outline text-xs">Close</button>
                <button wire:click="confirmFailedDelivery" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-black transition-colors">
                    Save Failed Record
                </button>
            </div>

        </div>
    </div>
    @endif


    <!-- ========================================================================= -->
    <!-- DELIVERY RUN SHEET PRINTABLE MODAL                                        -->
    <!-- ========================================================================= -->
    @if($showDeliverySheetModal)
    <div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl border border-slate-200 max-w-2xl w-full p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
            
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <div>
                    <h3 class="text-lg font-black text-slate-900">BAQQALA GROCERY — DELIVERY RUN SHEET</h3>
                    <p class="text-xs text-slate-500">Date: {{ now()->format('d M Y, H:i') }}</p>
                </div>
                <button wire:click="$set('showDeliverySheetModal', false)" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
            </div>

            <div class="space-y-3 text-xs">
                @php $totalCod = 0; @endphp
                @foreach($deliverySheetOrders as $idx => $dso)
                @php $totalCod += (float) ($dso['total_amount'] ?? 0); @endphp
                <div class="p-3 rounded-xl border border-slate-200 flex items-center justify-between font-mono">
                    <div>
                        <div class="font-black text-slate-900 text-sm">
                            {{ $idx + 1 }}. {{ $dso['order_number'] }} — {{ $dso['customer_villa'] ? 'Villa ' . $dso['customer_villa'] : 'No Villa' }}
                        </div>
                        <div class="text-slate-600 text-xs mt-0.5">
                            Customer: {{ $dso['customer_name_snapshot'] ?? 'Valued Customer' }} ({{ $dso['customer_phone_snapshot'] ?? '' }})
                        </div>
                        <div class="text-slate-500 text-[11px] mt-0.5">
                            Address: {{ $dso['customer_address'] ?? '' }}
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-base font-black text-emerald-800">
                            AED {{ number_format($dso['total_amount'], 2) }}
                        </div>
                        <div class="text-[10px] text-amber-700 font-bold uppercase">
                            {{ $dso['payment_method'] ?? 'COD' }}
                        </div>
                    </div>
                </div>
                @endforeach

                <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl flex items-center justify-between font-mono text-sm font-black text-emerald-900">
                    <span>TOTAL COD TO COLLECT:</span>
                    <span>AED {{ number_format($totalCod, 2) }}</span>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button onclick="window.print()" class="btn-glow py-2 px-4 text-xs font-black">
                    🖨 Print Delivery Run Sheet
                </button>
                <button wire:click="$set('showDeliverySheetModal', false)" class="btn-outline text-xs">Close</button>
            </div>

        </div>
    </div>
    @endif

    <!-- ORDERS IMPORT MODAL -->
    @if($showImportModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
        <div class="bg-white rounded-3xl p-6 w-full max-w-lg shadow-2xl space-y-4 border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <h3 class="font-black text-slate-900 text-base">Import Customer Orders</h3>
                </div>
                <button wire:click="$set('showImportModal', false)" class="text-slate-400 hover:text-slate-600 font-bold text-xl">&times;</button>
            </div>

            <!-- Download Orders Template -->
            <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl flex items-center justify-between gap-4">
                <div>
                    <h4 class="font-bold text-emerald-950 text-xs">1. Download Order Template</h4>
                    <p class="text-[11px] text-emerald-800 mt-0.5">Pre-formatted with customer name, phone, villa, address, items, and totals.</p>
                </div>
                <a href="/api/v1/admin/orders/import-template" download="Baqqala_Orders_Import_Template.csv" class="btn-glow py-1.5 px-3 text-xs font-bold shrink-0">
                    Download
                </a>
            </div>

            <!-- File Upload Form -->
            <form wire:submit.prevent="processOrderImport" class="space-y-4 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">2. Select Order File (.csv)</label>
                    <input type="file" wire:model="orderImportFile" required accept=".csv,.txt" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-100 file:text-slate-800 hover:file:bg-slate-200">
                    <div wire:loading wire:target="orderImportFile" class="text-[10px] text-emerald-600 font-bold mt-1">Reading orders file...</div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button type="button" wire:click="$set('showImportModal', false)" class="btn-outline py-2 px-4 text-xs font-bold">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" class="btn-glow py-2 px-5 text-xs font-bold">
                        <span wire:loading.remove wire:target="processOrderImport">Execute Import</span>
                        <span wire:loading wire:target="processOrderImport">Importing Orders...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>
