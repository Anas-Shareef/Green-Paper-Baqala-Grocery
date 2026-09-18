<div class="p-6 space-y-6">
    
    <!-- Header & KPIs -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200/80 pb-5">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                Stock Receiving Station
                <span class="badge badge-emerald">Datalogic QuickScan Lite Active</span>
            </h1>
            <p class="text-xs text-slate-500 mt-1">Authoritative inbound stock workflow. Scan delivery barcodes to accumulate quantities in place, verify wholesale costs, and record immutable Goods Received Notes (GRN).</p>
        </div>

        <div class="flex items-center gap-2">
            <button wire:click="$set('viewMode', '{{ $viewMode === 'station' ? 'history' : 'station' }}')" class="btn-outline text-xs py-2">
                {{ $viewMode === 'station' ? 'View GRN History' : 'Back to Receiving Station' }}
            </button>
            <button wire:click="resetSession" class="btn-glow text-xs py-2">
                + New Receipt
            </button>
        </div>
    </div>

    <!-- KPIs Bar -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Draft Receipts</div>
            <div class="text-2xl font-black text-slate-800 mt-1 font-mono">{{ $kpis['draft_count'] }}</div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-[10px] font-bold uppercase tracking-wider text-amber-500">Pending Review</div>
            <div class="text-2xl font-black text-amber-600 mt-1 font-mono">{{ $kpis['pending_count'] }}</div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-600">Received Today</div>
            <div class="text-2xl font-black text-emerald-700 mt-1 font-mono">{{ $kpis['received_today_count'] }} (AED {{ number_format($kpis['received_today_value'], 2) }})</div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="text-[10px] font-bold uppercase tracking-wider text-indigo-600">Month Total Value</div>
            <div class="text-2xl font-black text-indigo-700 mt-1 font-mono">AED {{ number_format($kpis['month_total_value'], 2) }}</div>
        </div>
    </div>

    @if(session()->has('message'))
    <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-semibold">
        {{ session('message') }}
    </div>
    @endif
    @if(session()->has('error'))
    <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl text-xs font-semibold">
        {{ session('error') }}
    </div>
    @endif

    @if($viewMode === 'station')
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- LEFT 8 COLS: Scanner Bar & Items Table -->
        <div class="lg:col-span-8 space-y-5">
            
            <!-- Scanner Wedge Box -->
            <div class="bg-slate-900 text-white rounded-2xl p-5 shadow-lg border border-slate-700 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="font-extrabold text-sm tracking-wide text-emerald-400 uppercase flex items-center gap-2">
                        <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
                        ⚡ Scanner Ready (USB Keyboard Wedge)
                    </span>
                    <span class="text-xs text-slate-400 font-mono">Suffix: ENTER</span>
                </div>

                <form wire:submit.prevent="scanBarcode" class="flex gap-2">
                    <input type="text" 
                           wire:model.defer="barcodeInput" 
                           placeholder="Scan product barcode (Datalogic sends barcode + ENTER)..." 
                           autofocus
                           class="flex-1 bg-slate-950 border-2 border-emerald-500/60 rounded-xl px-4 py-3 font-mono font-bold text-white text-base focus:outline-none focus:border-emerald-400 placeholder:text-slate-500">
                    <button type="submit" class="btn-glow py-3 px-5">
                        Capture Scan
                    </button>
                </form>

                <div class="flex items-center justify-between text-[11px] text-slate-400 font-mono">
                    <span>Last Barcode: <strong class="text-white">{{ $lastScannedBarcode ?: 'None' }}</strong></span>
                    <span>Status: <strong class="text-emerald-400">{{ $scannerStatus }}</strong></span>
                </div>
            </div>

            <!-- Items Table -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <h2 class="font-bold text-base text-slate-900">
                        Received Items ({{ count($items) }})
                    </h2>
                </div>

                @if(empty($items))
                <div class="p-10 text-center text-slate-400 text-xs">
                    Receiving session empty. Scan products using your Datalogic barcode scanner or enter barcodes manually.
                </div>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-500 uppercase text-[10px] font-bold border-b border-slate-200/60">
                            <tr>
                                <th class="py-3 px-4">Item</th>
                                <th class="py-3 px-3 text-center w-20">Received</th>
                                <th class="py-3 px-3 text-center w-16">Damaged</th>
                                <th class="py-3 px-3 text-right w-24">Cost (AED)</th>
                                <th class="py-3 px-3 text-right w-24">Subtotal</th>
                                <th class="py-3 px-3 text-center w-28">Expiry</th>
                                <th class="py-3 px-3 text-center w-8"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            @foreach($items as $idx => $it)
                            <tr class="hover:bg-slate-50/70">
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-900">{{ $it['product_name'] }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono">{{ $it['barcode'] }}</div>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <input type="number" min="1" wire:model="items.{{ $idx }}.quantity_received" wire:change="recalculateLine({{ $idx }})" class="w-16 px-2 py-1 text-center font-mono font-bold bg-emerald-50 border border-emerald-300 rounded-md">
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <input type="number" min="0" wire:model="items.{{ $idx }}.quantity_damaged" wire:change="recalculateLine({{ $idx }})" class="w-14 px-2 py-1 text-center font-mono bg-slate-50 border border-slate-200 rounded-md text-rose-600">
                                </td>
                                <td class="py-3 px-3 text-right">
                                    <input type="number" step="0.01" min="0" wire:model="items.{{ $idx }}.unit_cost" wire:change="recalculateLine({{ $idx }})" class="w-20 px-2 py-1 text-right font-mono font-bold bg-slate-50 border border-slate-200 rounded-md">
                                </td>
                                <td class="py-3 px-3 text-right font-mono font-bold text-slate-900">
                                    AED {{ number_format($it['subtotal'], 2) }}
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <input type="date" wire:model="items.{{ $idx }}.expiry_date" class="w-24 px-1 py-1 text-[10px] font-mono bg-slate-50 border border-slate-200 rounded-md">
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <button wire:click="removeItem({{ $idx }})" class="text-slate-400 hover:text-rose-600 font-bold">&times;</button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>

        </div>

        <!-- RIGHT 4 COLS: Header & Totals -->
        <div class="lg:col-span-4 space-y-5">
            
            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs space-y-4 text-xs">
                <div class="flex justify-between items-center border-b border-slate-100 pb-2 font-mono">
                    <span class="text-slate-500">GRN Reference:</span>
                    <span class="font-bold text-slate-900">{{ $grnNumber }}</span>
                </div>

                <div>
                    <div class="flex justify-between mb-1">
                        <label class="font-bold text-slate-700">Supplier *</label>
                        <button type="button" wire:click="$set('showSupplierModal', true)" class="text-emerald-600 font-bold hover:underline">+ Add</button>
                    </div>
                    <select wire:model="supplierId" class="input-field">
                        <option value="">-- Choose Supplier --</option>
                        @foreach($suppliers as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Invoice #</label>
                        <input type="text" wire:model.defer="supplierInvoiceNumber" placeholder="INV-000" class="input-field">
                    </div>
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Invoice Date</label>
                        <input type="date" wire:model.defer="invoiceDate" class="input-field">
                    </div>
                </div>

                <div>
                    <label class="font-bold text-slate-700 block mb-1">Notes</label>
                    <textarea wire:model.defer="notes" rows="2" placeholder="Damaged items, remarks..." class="input-field"></textarea>
                </div>

                <div class="pt-3 border-t border-slate-100 space-y-2 font-mono">
                    <div class="flex justify-between text-slate-600">
                        <span>Items Count:</span>
                        <span class="font-bold text-slate-900">{{ count($items) }}</span>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <span>Total Units:</span>
                        <span class="font-bold text-slate-900">{{ array_sum(array_column($items, 'quantity_received')) }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-slate-900 text-sm pt-2 border-t border-slate-200">
                        <span>Grand Total:</span>
                        <span class="text-emerald-600">AED {{ number_format(array_sum(array_column($items, 'subtotal')), 2) }}</span>
                    </div>
                </div>

                <div class="pt-3 space-y-2">
                    <button wire:click="confirmReceipt" class="w-full btn-glow py-3 text-center justify-center font-bold">
                        Confirm Receipt & Update Stock
                    </button>
                    <button wire:click="saveDraft" class="w-full btn-outline py-2 text-center justify-center font-bold">
                        Save as Draft
                    </button>
                </div>
            </div>

        </div>

    </div>
    @else

    <!-- HISTORY VIEW -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-100 font-bold text-slate-900">
            Goods Received Notes (GRN) Ledger
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase text-[10px] font-bold border-b border-slate-200/60">
                    <tr>
                        <th class="py-3 px-4">GRN #</th>
                        <th class="py-3 px-4">Supplier</th>
                        <th class="py-3 px-3">Invoice #</th>
                        <th class="py-3 px-3">Date</th>
                        <th class="py-3 px-3 text-center">Items</th>
                        <th class="py-3 px-4 text-right">Total Amount</th>
                        <th class="py-3 px-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium font-mono">
                    @foreach($history as $rec)
                    <tr class="hover:bg-slate-50/70">
                        <td class="py-3 px-4 font-black text-slate-900">{{ $rec->grn_number }}</td>
                        <td class="py-3 px-4 font-sans font-bold text-slate-800">{{ $rec->supplier_name_snapshot ?? ($rec->supplier?->name ?? 'Local') }}</td>
                        <td class="py-3 px-3 text-slate-600">{{ $rec->supplier_invoice_number ?? '—' }}</td>
                        <td class="py-3 px-3 text-slate-500">{{ $rec->receiving_date }}</td>
                        <td class="py-3 px-3 text-center font-bold">{{ count($rec->items) }}</td>
                        <td class="py-3 px-4 text-right font-black text-slate-900">AED {{ number_format($rec->total_amount, 2) }}</td>
                        <td class="py-3 px-3 text-center font-sans">
                            <span class="badge {{ $rec->status === 'received' ? 'badge-emerald' : 'badge-slate' }}">
                                {{ $rec->status }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- UNKNOWN PRODUCT MODAL -->
    @if($showUnknownModal)
    <div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4 text-xs">
            <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                <h3 class="font-bold text-base text-slate-900">Unknown Barcode Scanned</h3>
                <button wire:click="$set('showUnknownModal', false)" class="text-slate-400">&times;</button>
            </div>

            <form wire:submit.prevent="createUnknownProduct" class="space-y-3">
                <div>
                    <label class="font-bold text-slate-700 block mb-1">Barcode</label>
                    <input type="text" wire:model.defer="unknownBarcode" readonly class="input-field font-mono font-bold text-amber-800 bg-amber-50">
                </div>
                <div>
                    <label class="font-bold text-slate-700 block mb-1">Product Title *</label>
                    <input type="text" wire:model.defer="newProductName" required placeholder="e.g. Fresh Milk 1L" class="input-field">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Wholesale Cost (AED) *</label>
                        <input type="number" step="0.01" min="0" wire:model.defer="newWholesaleCost" required class="input-field font-mono">
                    </div>
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Retail Price (AED) *</label>
                        <input type="number" step="0.01" min="0" wire:model.defer="newRetailPrice" required class="input-field font-mono">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" wire:click="$set('showUnknownModal', false)" class="btn-outline">Cancel</button>
                    <button type="submit" class="btn-glow">Create & Add to Receipt</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- QUICK SUPPLIER MODAL -->
    @if($showSupplierModal)
    <div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4 text-xs">
            <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                <h3 class="font-bold text-base text-slate-900">Add Registered Supplier</h3>
                <button wire:click="$set('showSupplierModal', false)" class="text-slate-400">&times;</button>
            </div>

            <form wire:submit.prevent="createQuickSupplier" class="space-y-3">
                <div>
                    <label class="font-bold text-slate-700 block mb-1">Supplier Company Name *</label>
                    <input type="text" wire:model.defer="newSupplierName" required placeholder="e.g. Al Ain Dairy LLC" class="input-field">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Phone</label>
                        <input type="text" wire:model.defer="newSupplierPhone" placeholder="+971 4 000 0000" class="input-field">
                    </div>
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">TRN / Tax Number</label>
                        <input type="text" wire:model.defer="newSupplierTax" placeholder="TRN-100xxxx" class="input-field font-mono">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" wire:click="$set('showSupplierModal', false)" class="btn-outline">Cancel</button>
                    <button type="submit" class="btn-glow">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>
