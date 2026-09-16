<div class="p-6 space-y-6">
    
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200/80 pb-5">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                Stock Receiving Station
                <span class="badge badge-emerald">Barcode Scanner Active</span>
            </h1>
            <p class="text-xs text-slate-500 mt-1">Scan stock delivery packages, enter received quantities, and update wholesale cost ledgers.</p>
        </div>
    </div>

    @if(session()->has('message'))
    <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm font-semibold">
        {{ session('message') }}
    </div>
    @endif
    @if(session()->has('info'))
    <div class="p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-sm font-semibold">
        {{ session('info') }}
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- LEFT 2 COLS: Scan & Receive Panel -->
        <div class="lg:col-span-2 space-y-5">
            
            <!-- Scan Barcode Box -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 space-y-4 shadow-xs">
                <h3 class="font-extrabold text-slate-900 text-base flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                    Step 1: Scan Barcode or Select Product
                </h3>

                <form wire:submit.prevent="scanBarcode" class="flex gap-2">
                    <input type="text" 
                           wire:model.defer="barcodeInput" 
                           placeholder="Scan item barcode e.g. 8901288030609..." 
                           autofocus
                           class="flex-1 input-field font-mono font-bold text-sm py-3 border-2 border-emerald-500/50">
                    <button type="submit" class="btn-glow py-3">
                        Scan Item
                    </button>
                </form>

                <div class="pt-2">
                    <label class="block text-xs text-slate-600 font-bold mb-1">Or Pick From Catalog List:</label>
                    <select wire:change="selectProduct($event.target.value)" class="input-field">
                        <option value="">-- Choose Product --</option>
                        @foreach($productsList as $pl)
                        <option value="{{ $pl->id }}" {{ $selectedProductId === $pl->id ? 'selected' : '' }}>
                            {{ $pl->name }} (Barcode: {{ $pl->barcode }} | Stock: {{ $pl->stock_quantity }})
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Selected Item Receiving Details Form -->
            @if($selectedProduct)
            <div class="bg-white border-2 border-emerald-500/40 rounded-2xl p-6 space-y-4 shadow-sm">
                <div class="flex justify-between items-start border-b border-slate-100 pb-4">
                    <div>
                        <span class="badge badge-emerald">Selected Item</span>
                        <h2 class="text-xl font-black text-slate-900 mt-1.5">{{ $selectedProduct->name }}</h2>
                        <p class="text-xs text-slate-500 font-mono mt-0.5">Barcode: {{ $selectedProduct->barcode }} • Unit: {{ $selectedProduct->unit }}</p>
                    </div>
                    <div class="text-right bg-slate-50 p-3 rounded-xl border border-slate-200 font-mono">
                        <div class="text-[10px] text-slate-500 uppercase font-bold tracking-wider">Current System Stock</div>
                        <div class="text-2xl font-black text-emerald-600">{{ $selectedProduct->stock_quantity }}</div>
                    </div>
                </div>

                <form wire:submit.prevent="submitReceiving" class="space-y-4 text-xs">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Quantity Received (+)</label>
                            <input type="number" min="1" wire:model.defer="receivingQuantity" required class="input-field font-mono font-bold text-lg py-3">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Stock Movement Reason</label>
                            <input type="text" wire:model.defer="reason" required class="input-field py-3">
                        </div>
                    </div>

                    <div class="bg-slate-50 p-3.5 rounded-xl text-xs space-y-1.5 font-mono text-slate-700 border border-slate-200">
                        <div class="flex justify-between">
                            <span>Stock Before:</span>
                            <span>{{ $selectedProduct->stock_quantity }} units</span>
                        </div>
                        <div class="flex justify-between font-bold text-emerald-700 text-sm pt-1.5 border-t border-slate-200">
                            <span>New Stock Level After Receiving:</span>
                            <span>{{ $selectedProduct->stock_quantity + (int)$receivingQuantity }} units</span>
                        </div>
                    </div>

                    <button type="submit" class="w-full btn-glow py-3.5 text-center justify-center">
                        Confirm Receiving & Update Ledger
                    </button>
                </form>
            </div>
            @endif

        </div>

        <!-- RIGHT COL: Recent Stock Receiving History -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 space-y-4 shadow-xs h-fit">
            <h3 class="font-extrabold text-slate-900 text-base">Recent Stock Purchase History</h3>

            <div class="space-y-3 max-h-[500px] overflow-y-auto pr-1">
                @foreach($recentReceivings as $rr)
                <div class="p-3 bg-slate-50 border border-slate-200/80 rounded-xl text-xs space-y-1 font-medium">
                    <div class="flex justify-between items-start">
                        <div class="font-bold text-slate-900 truncate max-w-[180px]">{{ $rr->product?->name ?? 'Unknown Item' }}</div>
                        <span class="badge badge-emerald font-mono">+{{ $rr->quantity }} units</span>
                    </div>
                    <div class="text-[10px] text-slate-500 flex justify-between pt-1">
                        <span>By: {{ $rr->created_by ?? 'Admin' }}</span>
                        <span>{{ $rr->created_at->format('d M, H:i') }}</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

    </div>

    <!-- MODAL: Rapid Creation for Unknown Barcode -->
    @if($showUnknownModal)
    <div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-2xl p-6 w-full max-w-md shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="font-extrabold text-slate-900 text-base">Unknown Barcode Received</h3>
                <button wire:click="$set('showUnknownModal', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <form wire:submit.prevent="saveUnknownProduct" class="space-y-3 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Scanned Barcode</label>
                    <input type="text" wire:model.defer="newBarcode" readonly class="input-field text-amber-700 font-mono font-bold">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Product Title *</label>
                    <input type="text" wire:model.defer="newName" required placeholder="e.g. Organic Avocados 500g" class="input-field">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Retail Price (₹) *</label>
                        <input type="number" step="0.50" min="0" wire:model.defer="newRetailPrice" required class="input-field font-mono">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Wholesale Cost (₹) *</label>
                        <input type="number" step="0.50" min="0" wire:model.defer="newWholesaleCost" required class="input-field font-mono font-bold">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Received Quantity *</label>
                    <input type="number" min="1" wire:model.defer="receivingQuantity" required class="input-field font-mono font-bold">
                </div>

                <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-100">
                    <button type="button" wire:click="$set('showUnknownModal', false)" class="btn-outline">Cancel</button>
                    <button type="submit" class="btn-glow">Create & Receive Stock</button>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>
