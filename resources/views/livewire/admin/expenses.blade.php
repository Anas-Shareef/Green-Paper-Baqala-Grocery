<div class="p-6 space-y-6">
    
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                Business Expense Tracking
                <span class="badge badge-amber">Net Profit Deductions</span>
            </h1>
            <p class="text-xs text-slate-500 mt-1">Log delivery fuel, packaging, utilities, spoiled goods, and operational expenses.</p>
        </div>
        
        <div class="flex items-center gap-3">
            <button wire:click="$set('showModal', true)" class="btn-glow flex items-center gap-2">
                + Log Business Expense
            </button>
        </div>
    </div>

    @if(session()->has('message'))
    <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm font-semibold">
        {{ session('message') }}
    </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="kpi-card p-5 space-y-1">
            <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">This Month Expenses</div>
            <div class="text-3xl font-black text-amber-700 font-mono">₹{{ number_format($totalExpensesThisMonth, 2) }}</div>
            <div class="text-[10px] text-slate-400 font-medium">Deducted from Net Profit statement</div>
        </div>
    </div>

    <!-- Expense Table -->
    <div class="bg-white border border-slate-200/80 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700">
                <thead class="tbl-head">
                    <tr>
                        <th class="p-3.5">Expense Date</th>
                        <th class="p-3.5">Category</th>
                        <th class="p-3.5">Description</th>
                        <th class="p-3.5">Payment Method</th>
                        <th class="p-3.5 text-right">Amount (₹)</th>
                        <th class="p-3.5 text-right">Logged By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($expenses as $e)
                    <tr class="tbl-row">
                        <td class="tbl-cell font-mono font-bold text-slate-900">{{ $e->expense_date->format('d M Y') }}</td>
                        <td class="tbl-cell">
                            <span class="badge badge-amber">{{ $e->category?->name ?? 'General' }}</span>
                        </td>
                        <td class="tbl-cell text-slate-900 font-semibold">{{ $e->description }}</td>
                        <td class="tbl-cell font-mono text-slate-500">{{ $e->payment_method }}</td>
                        <td class="tbl-cell text-right font-mono font-black text-rose-600 text-sm">₹{{ number_format($e->amount, 2) }}</td>
                        <td class="tbl-cell text-right text-slate-400 text-[11px]">{{ $e->created_by ?? 'Admin' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-slate-400 font-medium">No expenses logged yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100">
            {{ $expenses->links() }}
        </div>
    </div>

    <!-- MODAL: Log Expense -->
    @if($showModal)
    <div class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-2xl p-6 w-full max-w-md shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="font-extrabold text-slate-900 text-base">Log New Business Expense</h3>
                <button wire:click="$set('showModal', false)" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <form wire:submit.prevent="saveExpense" class="space-y-3 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Expense Category *</label>
                    <select wire:model.defer="categoryId" required class="input-field">
                        @foreach($categories as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Amount (₹) *</label>
                        <input type="number" step="0.50" min="0.50" wire:model.defer="amount" required class="input-field font-mono">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Expense Date *</label>
                        <input type="date" wire:model.defer="expenseDate" required class="input-field font-mono">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Description *</label>
                    <input type="text" wire:model.defer="description" required placeholder="e.g. Delivery scooter fuel fill up" class="input-field">
                </div>

                <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-100">
                    <button type="button" wire:click="$set('showModal', false)" class="btn-outline">Cancel</button>
                    <button type="submit" class="btn-glow">Save Expense</button>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>
