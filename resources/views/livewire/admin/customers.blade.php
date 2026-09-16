<div class="p-6 space-y-6">
    
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                Customer CRM & Lifetime Value
                <span class="badge badge-emerald">Phone Indexed</span>
            </h1>
            <p class="text-xs text-slate-500 mt-1">Searchable customer database with villa locations, total spending, order history, and CSV batch importer.</p>
        </div>
        
        <div class="flex items-center gap-3">
            <button wire:click="$set('showImporterModal', true)" class="btn-glow flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                Import Customer CSV
            </button>
        </div>
    </div>

    @if(session()->has('message'))
    <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm font-semibold">
        {{ session('message') }}
    </div>
    @endif
    @if(session()->has('error'))
    <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-sm font-semibold">
        {{ session('error') }}
    </div>
    @endif

    <!-- Filters Bar -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm">
        <div>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by customer name, phone, villa number, zone..." class="input-field">
        </div>
        <div>
            <select wire:model.live="selectedZone" class="input-field">
                <option value="">All Delivery Zones</option>
                @foreach($zones as $z)
                <option value="{{ $z }}">{{ $z }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Customers Table -->
    <div class="bg-white border border-slate-200/80 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700">
                <thead class="tbl-head">
                    <tr>
                        <th class="p-3.5">Customer Name</th>
                        <th class="p-3.5">Phone / WhatsApp</th>
                        <th class="p-3.5">Villa & Zone</th>
                        <th class="p-3.5 text-center">Total Orders</th>
                        <th class="p-3.5 text-right">Lifetime Value (LTV)</th>
                        <th class="p-3.5 text-right">Avg Order Value</th>
                        <th class="p-3.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($customers as $c)
                    <tr class="tbl-row">
                        <td class="tbl-cell font-extrabold text-slate-900 text-sm">
                            {{ $c->name }}
                        </td>
                        <td class="tbl-cell font-mono text-emerald-700 font-bold">
                            +{{ $c->phone }}
                        </td>
                        <td class="tbl-cell">
                            <div class="font-semibold text-slate-800">{{ $c->villa_number ?? 'No Villa' }}</div>
                            <div class="text-[10px] text-slate-500 mt-0.5">{{ $c->zone ?? 'Zone A' }}</div>
                        </td>
                        <td class="tbl-cell text-center font-mono font-bold text-slate-900">
                            {{ $c->total_orders }}
                        </td>
                        <td class="tbl-cell text-right font-mono font-black text-emerald-700 text-sm">
                            ₹{{ number_format($c->total_spent, 2) }}
                        </td>
                        <td class="tbl-cell text-right font-mono text-slate-500">
                            ₹{{ number_format($c->average_order_value, 2) }}
                        </td>
                        <td class="tbl-cell text-right">
                            <button wire:click="viewCustomer({{ $c->id }})" class="px-3 py-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 rounded-lg text-[11px] font-bold transition-all">
                                View CRM Profile
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center text-slate-400 font-medium">No customers found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100">
            {{ $customers->links() }}
        </div>
    </div>

    <!-- MODAL 1: CSV Importer Modal -->
    @if($showImporterModal)
    <div class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-2xl p-6 w-full max-w-lg shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="font-extrabold text-slate-900 text-base">Import Customers CSV</h3>
                <button wire:click="$set('showImporterModal', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <form wire:submit.prevent="processImport" class="space-y-4 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Select CSV File * (Expected columns: Name, Phone, Villa Number, Zone)</label>
                    <input type="file" wire:model="csvFile" required accept=".csv,.txt" class="input-field py-2 file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-emerald-600 file:text-white">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Duplicate Phone Handling Strategy *</label>
                    <select wire:model="duplicateStrategy" class="input-field">
                        <option value="skip">Skip duplicates (Keep existing customer records)</option>
                        <option value="update">Update existing records with CSV info</option>
                        <option value="merge">Merge missing villa/zone details</option>
                    </select>
                </div>

                @if($importSummary)
                <div class="bg-slate-50 p-4 rounded-xl space-y-2 border border-slate-200 font-mono text-xs">
                    <div class="font-bold text-emerald-700">Import Summary Report:</div>
                    <div class="flex justify-between"><span>Total Rows Read:</span><span class="text-slate-800">{{ $importSummary['total_rows'] }}</span></div>
                    <div class="flex justify-between text-emerald-700"><span>Imported / Updated:</span><span>{{ $importSummary['imported'] }}</span></div>
                    <div class="flex justify-between text-amber-700"><span>Duplicates Handled:</span><span>{{ $importSummary['duplicates'] }}</span></div>
                    <div class="flex justify-between text-rose-700"><span>Invalid Rows:</span><span>{{ $importSummary['invalid'] }}</span></div>
                </div>
                @endif

                <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-100">
                    <button type="button" wire:click="$set('showImporterModal', false)" class="btn-outline">Close</button>
                    <button type="submit" class="btn-glow">Run Importer</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- MODAL 2: Customer CRM Profile Drawer -->
    @if($showCustomerModal && $viewingCustomer)
    <div class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex items-center justify-end">
        <div class="bg-white border-l border-slate-200 h-full w-full max-w-md p-6 shadow-2xl space-y-6 overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="font-extrabold text-slate-900 text-lg">{{ $viewingCustomer->name }}</h3>
                    <p class="text-xs text-emerald-700 font-mono mt-0.5">+{{ $viewingCustomer->phone }}</p>
                </div>
                <button wire:click="$set('showCustomerModal', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <div class="grid grid-cols-2 gap-3 text-xs">
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
                    <div class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Villa Location</div>
                    <div class="text-sm font-bold text-slate-900 mt-1">{{ $viewingCustomer->villa_number ?? 'N/A' }}</div>
                </div>
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
                    <div class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Delivery Zone</div>
                    <div class="text-sm font-bold text-slate-900 mt-1">{{ $viewingCustomer->zone ?? 'Zone A' }}</div>
                </div>
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
                    <div class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Total Orders</div>
                    <div class="text-sm font-bold text-emerald-700 mt-1">{{ $viewingCustomer->total_orders }}</div>
                </div>
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
                    <div class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Lifetime Spending</div>
                    <div class="text-sm font-black text-emerald-700 font-mono mt-1">₹{{ number_format($viewingCustomer->total_spent, 2) }}</div>
                </div>
            </div>

            <!-- Category Preferences Breakdown -->
            <div class="space-y-3">
                <h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider">Category Preferences Breakdown</h4>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2">
                    @forelse($viewingCustomer->category_preferences as $catName => $pct)
                    <div class="space-y-1">
                        <div class="flex justify-between text-xs font-semibold">
                            <span class="text-slate-700">{{ $catName }}</span>
                            <span class="text-emerald-700 font-mono">{{ $pct }}%</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                            <div class="bg-emerald-600 h-full rounded-full" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                    @empty
                    <div class="text-xs text-slate-400">No category purchase data yet.</div>
                    @endforelse
                </div>
            </div>

            <!-- Order History List -->
            <div class="space-y-3">
                <h4 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider">Order History</h4>
                <div class="space-y-2">
                    @foreach($viewingCustomer->orders as $o)
                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs space-y-1">
                        <div class="flex justify-between font-mono font-bold">
                            <span class="text-slate-900">{{ $o->order_number }}</span>
                            <span class="text-emerald-700">₹{{ number_format($o->total_amount, 2) }}</span>
                        </div>
                        <div class="text-[10px] text-slate-500 flex justify-between">
                            <span>Status: {{ strtoupper($o->status) }}</span>
                            <span>{{ $o->created_at->format('d M Y') }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

</div>
