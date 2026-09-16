<div x-data="{ 
        initScanner() {
            this.$refs.scannerInput.focus();
            // Continuous focus keeper loop
            setInterval(() => {
                const active = document.activeElement;
                const modalOpen = $wire.showNewProductModal || $wire.showReceiptModal;
                if (!modalOpen && active !== this.$refs.scannerInput && active.tagName !== 'INPUT' && active.tagName !== 'SELECT' && active.tagName !== 'TEXTAREA') {
                    this.$refs.scannerInput.focus();
                }
            }, 800);
        }
    }" 
    x-init="initScanner()"
    @keydown.window.escape="$wire.clearCart()"
    class="flex-1 flex flex-col md:flex-row h-full min-h-screen bg-slate-50 overflow-hidden text-slate-900">

    <!-- Toast Notification Banner -->
    @if($toastMessage)
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" 
         class="fixed top-4 right-4 z-50 px-4 py-3 rounded-xl shadow-2xl flex items-center gap-3 text-sm font-semibold border backdrop-blur-xl transition-all duration-300
                {{ $toastType === 'success' ? 'bg-emerald-50 border-emerald-300 text-emerald-800 shadow-emerald-600/10' : '' }}
                {{ $toastType === 'error' ? 'bg-rose-50 border-rose-300 text-rose-800 shadow-rose-600/10' : '' }}
                {{ $toastType === 'warning' ? 'bg-amber-50 border-amber-300 text-amber-800 shadow-amber-600/10' : '' }}">
        <span>{{ $toastMessage }}</span>
        <button @click="show = false" class="text-slate-400 hover:text-slate-700">&times;</button>
    </div>
    @endif

    <!-- LEFT SECTION: Scanner Input & Quick Product Grid -->
    <div class="flex-1 flex flex-col p-4 md:p-6 border-r border-slate-200/80 gap-5 overflow-y-auto bg-slate-50">
        
        <!-- Header & Scanner Bar -->
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                        <span>Physical POS Counter</span>
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    </h2>
                    <p class="text-xs text-slate-500">Barcode keyboard wedge active. Scan product barcode directly.</p>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <span class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-slate-600 shadow-xs">Scanner: <strong class="text-emerald-600">Datalogic Ready</strong></span>
                </div>
            </div>

            <!-- DEDICATED SCANNER INPUT -->
            <form wire:submit.prevent="scanBarcode" class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <svg class="w-6 h-6 text-emerald-600 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                </div>
                <input x-ref="scannerInput" 
                       type="text" 
                       wire:model.defer="barcodeInput"
                       placeholder="SCAN BARCODE HERE (or type barcode & press Enter)... e.g. 8901288030609" 
                       autofocus
                       class="w-full pl-12 pr-28 py-4 bg-white border-2 border-emerald-500/60 focus:border-emerald-600 rounded-2xl text-slate-900 placeholder-slate-400 font-mono text-base font-bold shadow-md shadow-emerald-500/5 outline-none transition-all">
                <button type="submit" class="absolute right-2 top-2 bottom-2 px-5 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-sm rounded-xl transition-all shadow-md">
                    SCAN
                </button>
            </form>
        </div>

        <!-- Quick Tap Products Grid -->
        <div class="space-y-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Quick Tap Catalog Items</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                @foreach($quickProducts as $qp)
                <button wire:click="addToCart({{ $qp->id }})" 
                        class="p-3 bg-white hover:bg-slate-100/80 border border-slate-200/80 hover:border-emerald-500/50 rounded-xl text-left transition-all group flex flex-col justify-between h-28 relative overflow-hidden shadow-xs">
                    <div>
                        <div class="text-xs font-bold text-slate-900 group-hover:text-emerald-600 line-clamp-2 leading-snug">{{ $qp->name }}</div>
                        <div class="text-[10px] text-slate-400 mt-1 font-mono">{{ $qp->barcode }}</div>
                    </div>
                    <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-100">
                        <span class="text-xs font-black text-emerald-600">₹{{ number_format($qp->retail_price, 2) }}</span>
                        <span class="text-[10px] font-semibold text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded">Stock: {{ $qp->stock_quantity }}</span>
                    </div>
                </button>
                @endforeach
            </div>
        </div>
    </div>

    <!-- RIGHT SECTION: Active Cart & Fast Checkout (White Mode) -->
    <div class="w-full md:w-[420px] lg:w-[480px] bg-white flex flex-col justify-between h-full border-t md:border-t-0 md:border-l border-slate-200/80 shadow-md">
        
        <!-- Cart Header -->
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-white">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"></path></svg>
                <h3 class="font-bold text-slate-900 text-base">Current POS Cart</h3>
                <span class="badge badge-emerald">{{ count($cart) }} Items</span>
            </div>
            @if(count($cart) > 0)
            <button wire:click="clearCart" class="text-xs text-rose-600 hover:text-rose-700 font-bold">Clear All</button>
            @endif
        </div>

        <!-- Cart Items List -->
        <div class="flex-1 overflow-y-auto p-4 space-y-3 divide-y divide-slate-100">
            @forelse($cart as $index => $item)
            <div class="pt-3 first:pt-0 flex items-center justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-bold text-slate-900 truncate">{{ $item['name'] }}</div>
                    <div class="text-xs text-slate-500 font-mono flex items-center gap-2 mt-0.5">
                        <span>₹{{ number_format($item['retail_price'], 2) }} / {{ $item['unit'] }}</span>
                        <span class="text-slate-300">•</span>
                        <span class="text-slate-400">Barcode: {{ $item['barcode'] }}</span>
                    </div>
                </div>

                <!-- Quantity Controls -->
                <div class="flex items-center gap-2">
                    <button wire:click="decrementQty({{ $index }})" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-sm flex items-center justify-center">-</button>
                    <span class="w-8 text-center font-bold text-sm text-slate-900 font-mono">{{ $item['quantity'] }}</span>
                    <button wire:click="incrementQty({{ $index }})" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-sm flex items-center justify-center">+</button>
                </div>

                <!-- Total -->
                <div class="text-right w-20">
                    <div class="text-sm font-black text-emerald-600">₹{{ number_format($item['retail_price'] * $item['quantity'], 2) }}</div>
                    <button wire:click="removeItem({{ $index }})" class="text-[10px] text-rose-600 hover:underline">Remove</button>
                </div>
            </div>
            @empty
            <div class="h-64 flex flex-col items-center justify-center text-center p-6 text-slate-400">
                <svg class="w-16 h-16 text-slate-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                <div class="font-bold text-slate-600 text-sm">Cart is Empty</div>
                <p class="text-xs text-slate-400 mt-1">Scan product barcodes or tap quick items to populate cart.</p>
            </div>
            @endforelse
        </div>

        <!-- Financial Summary & Complete Sale Footer -->
        <div class="p-4 bg-slate-50 border-t border-slate-200/80 space-y-4">
            
            <!-- Payment Method & Discount Selection -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Payment Method</label>
                    <select wire:model="paymentMethod" class="input-field text-xs font-semibold">
                        <option value="Cash">Cash Payment</option>
                        <option value="Card">Card Reader</option>
                        <option value="UPI">UPI / QR Code</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Discount (₹)</label>
                    <input type="number" step="0.50" min="0" wire:model.live="discountAmount" class="input-field text-xs font-semibold font-mono">
                </div>
            </div>

            <!-- Financial Totals Table -->
            <div class="space-y-1.5 text-xs text-slate-600 font-medium">
                <div class="flex justify-between">
                    <span>Subtotal:</span>
                    <span class="font-mono">₹{{ number_format($this->subtotal, 2) }}</span>
                </div>
                @if($discountAmount > 0)
                <div class="flex justify-between text-rose-600">
                    <span>Discount:</span>
                    <span class="font-mono">-₹{{ number_format($discountAmount, 2) }}</span>
                </div>
                @endif
                <div class="flex justify-between text-slate-500 text-[11px]">
                    <span>Est. Gross Profit:</span>
                    <span class="font-mono text-emerald-600">₹{{ number_format($this->estimatedProfit, 2) }}</span>
                </div>
                <div class="flex justify-between items-center text-lg font-black text-slate-900 pt-2 border-t border-slate-200">
                    <span>TOTAL DUE:</span>
                    <span class="text-2xl font-black text-emerald-600 font-mono">₹{{ number_format($this->total, 2) }}</span>
                </div>
            </div>

            <!-- COMPLETE SALE BUTTON -->
            <button wire:click="completeSale" 
                    @if(count($cart) === 0) disabled @endif
                    class="w-full btn-glow py-4 text-base uppercase tracking-wider rounded-2xl justify-center text-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                Complete POS Sale (Cash)
            </button>
        </div>
    </div>

    <!-- MODAL 1: Rapid Product Creation (Light Mode) -->
    @if($showNewProductModal)
    <div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-2xl p-6 w-full max-w-md shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h3 class="font-extrabold text-slate-900 text-lg">Product Not Found</h3>
                    <p class="text-xs text-amber-600 font-mono mt-0.5">Barcode: {{ $newBarcode }}</p>
                </div>
                <button wire:click="$set('showNewProductModal', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <form wire:submit.prevent="saveRapidProduct" class="space-y-3 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Product Name *</label>
                    <input type="text" wire:model.defer="newName" required placeholder="e.g. Fresh Mango 1kg" class="input-field">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Category *</label>
                    <select wire:model.defer="newCategoryId" required class="input-field">
                        @foreach($categories as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Retail Price (₹) *</label>
                        <input type="number" step="0.50" min="0" wire:model.defer="newRetailPrice" required class="input-field font-mono">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Wholesale Cost (₹) *</label>
                        <input type="number" step="0.50" min="0" wire:model.defer="newWholesaleCost" required class="input-field font-mono">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Initial Stock Qty *</label>
                        <input type="number" min="1" wire:model.defer="newInitialStock" required class="input-field font-mono">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Unit Description</label>
                        <input type="text" wire:model.defer="newUnit" placeholder="1 kg, 500g, 1 pack" class="input-field">
                    </div>
                </div>

                <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-100">
                    <button type="button" wire:click="$set('showNewProductModal', false)" class="btn-outline">Cancel</button>
                    <button type="submit" class="btn-glow">Save & Add to Cart</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- MODAL 2: Receipt Modal (Light Mode) -->
    @if($showReceiptModal && $lastCompletedOrder)
    <div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-2xl p-6 w-full max-w-sm shadow-2xl space-y-4 text-center">
            <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 border border-emerald-200 flex items-center justify-center mx-auto text-3xl font-black">
                ✓
            </div>
            <div>
                <h3 class="font-extrabold text-slate-900 text-xl">Sale Completed!</h3>
                <p class="text-xs text-emerald-600 font-mono mt-1">{{ $lastCompletedOrder->order_number }}</p>
            </div>

            <div class="bg-slate-50 p-4 rounded-xl text-xs space-y-2 text-left font-mono border border-slate-200">
                <div class="flex justify-between">
                    <span class="text-slate-500">Total Paid:</span>
                    <span class="text-emerald-700 font-bold">₹{{ number_format($lastCompletedOrder->total_amount, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Payment:</span>
                    <span class="text-slate-900 font-bold">{{ $lastCompletedOrder->payment_method }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Items Count:</span>
                    <span class="text-slate-900">{{ $lastCompletedOrder->items->count() }} items</span>
                </div>
            </div>

            <div class="space-y-2 pt-2">
                <a href="{{ route('invoice.download', ['order' => $lastCompletedOrder->order_number]) }}" target="_blank" class="w-full btn-glow py-3 justify-center text-xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Download PDF Thermal Receipt
                </a>
                <button wire:click="$set('showReceiptModal', false)" class="w-full btn-outline justify-center py-2.5 text-xs">
                    Close & Next Customer
                </button>
            </div>
        </div>
    </div>
    @endif

</div>
