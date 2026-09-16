<div class="p-6 space-y-6">
    
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200/80 pb-5">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                Products & Inventory Ledger
                <span class="badge badge-emerald">Barcode String Index</span>
            </h1>
            <p class="text-xs text-slate-500 mt-1">Manage grocery items, wholesale costs, retail prices, and manual stock adjustments with audit logging.</p>
        </div>
        
        <div class="flex items-center gap-3">
            <a href="/admin/receiving" class="btn-outline">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Fast Stock Receiving
            </a>
            <button wire:click="createProduct" class="btn-glow">
                + Add New Product
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
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
        <div>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name, barcode e.g. 8901288030609, brand..." class="input-field">
        </div>
        <div>
            <select wire:model.live="selectedCategory" class="input-field">
                <option value="">All Categories</option>
                @foreach($categories as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <select wire:model.live="stockFilter" class="input-field">
                <option value="">All Stock Levels</option>
                <option value="low_stock">Low Stock Warning</option>
                <option value="out_of_stock">Out of Stock</option>
            </select>
        </div>
    </div>

    <!-- Products Table (Light Mode) -->
    <div class="bg-white border border-slate-200/80 rounded-2xl overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700">
                <thead class="tbl-head">
                    <tr>
                        <th class="p-3.5">Barcode</th>
                        <th class="p-3.5">Product Name</th>
                        <th class="p-3.5">Category</th>
                        <th class="p-3.5 text-right">Wholesale Cost</th>
                        <th class="p-3.5 text-right">Retail Price</th>
                        <th class="p-3.5 text-right">Margin (Profit)</th>
                        <th class="p-3.5 text-center">Stock Qty</th>
                        <th class="p-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($products as $p)
                    <tr class="tbl-row">
                        <td class="tbl-cell font-mono font-bold text-amber-700">{{ $p->barcode }}</td>
                        <td class="tbl-cell">
                            <div class="font-bold text-slate-900 text-sm">{{ $p->name }}</div>
                            <div class="text-[10px] text-slate-500 mt-0.5">{{ $p->unit }} • {{ $p->brand ?? 'Generic' }}</div>
                        </td>
                        <td class="tbl-cell">
                            <span class="badge badge-amber">{{ $p->category?->name ?? 'Uncategorized' }}</span>
                        </td>
                        <td class="tbl-cell text-right font-mono text-rose-600 font-bold">₹{{ number_format($p->wholesale_cost, 2) }}</td>
                        <td class="tbl-cell text-right font-mono text-slate-900 font-bold">₹{{ number_format($p->retail_price, 2) }}</td>
                        <td class="tbl-cell text-right font-mono text-emerald-600 font-bold">
                            +₹{{ number_format($p->retail_price - $p->wholesale_cost, 2) }}
                        </td>
                        <td class="tbl-cell text-center">
                            @if($p->stock_quantity <= 0)
                            <span class="badge badge-rose">Out of Stock</span>
                            @elseif($p->stock_quantity <= $p->minimum_stock_level)
                            <span class="badge badge-amber">Low: {{ $p->stock_quantity }}</span>
                            @else
                            <span class="badge badge-emerald font-mono">{{ $p->stock_quantity }}</span>
                            @endif
                        </td>
                        <td class="tbl-cell text-right space-x-2">
                            <button wire:click="openAdjustmentModal({{ $p->id }})" class="btn-outline py-1 px-3 text-xs">Adjust Stock</button>
                            <button wire:click="editProduct({{ $p->id }})" class="btn-glow py-1 px-3 text-xs">Edit</button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="p-8 text-center text-slate-400 font-medium">No products matching your search query.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100">
            {{ $products->links() }}
        </div>
    </div>

    <!-- Product Create/Edit Modal (Light Mode) -->
    @if($showProductModal)
    <div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-2xl p-6 w-full max-w-lg shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="font-extrabold text-slate-900 text-base">{{ $editingProductId ? 'Edit Product' : 'Add New Grocery Product' }}</h3>
                <button wire:click="$set('showProductModal', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <form wire:submit.prevent="saveProduct" class="space-y-3 text-xs">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Barcode String *</label>
                        <input type="text" wire:model.defer="barcode" required placeholder="8901288030609" class="input-field font-mono">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Brand Name</label>
                        <input type="text" wire:model.defer="brand" placeholder="e.g. Almarai, Lays" class="input-field">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Product Title *</label>
                    <input type="text" wire:model.defer="name" required placeholder="Fresh Whole Milk 1L" class="input-field">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Category *</label>
                        <select wire:model.defer="categoryId" required class="input-field">
                            @foreach($categories as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Unit Description</label>
                        <input type="text" wire:model.defer="unit" placeholder="1 Litre, 500g" class="input-field">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Wholesale Cost (₹) *</label>
                        <input type="number" step="0.50" min="0" wire:model.defer="wholesaleCost" required class="input-field font-mono">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Retail Selling Price (₹) *</label>
                        <input type="number" step="0.50" min="0" wire:model.defer="retailPrice" required class="input-field font-mono">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Current Stock Qty *</label>
                        <input type="number" min="0" wire:model.defer="stockQuantity" required class="input-field font-mono">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Min Reorder Level *</label>
                        <input type="number" min="1" wire:model.defer="minimumStockLevel" required class="input-field font-mono">
                    </div>
                </div>

                <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-100">
                    <button type="button" wire:click="$set('showProductModal', false)" class="btn-outline">Cancel</button>
                    <button type="submit" class="btn-glow">Save Product</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Stock Adjustment Modal (Light Mode) -->
    @if($showAdjustmentModal)
    <div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-2xl p-6 w-full max-w-md shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="font-extrabold text-slate-900 text-base">Adjust Inventory Level</h3>
                <button wire:click="$set('showAdjustmentModal', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <form wire:submit.prevent="saveAdjustment" class="space-y-3 text-xs">
                <div class="bg-slate-50 p-3 rounded-xl border border-slate-200">
                    <div class="font-bold text-slate-900 text-sm">{{ $adjustProductName }}</div>
                    <div class="text-[11px] text-slate-500 mt-0.5">Current Stock in System: <strong class="text-emerald-600 font-mono">{{ $currentStock }}</strong></div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">New Stock Quantity *</label>
                    <input type="number" min="0" wire:model.defer="newStockQuantity" required class="input-field font-mono text-base font-bold">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Mandatory Reason *</label>
                    <textarea wire:model.defer="adjustmentReason" required placeholder="e.g., Physical count correction, damaged goods, spoiled produce..." class="input-field h-20 py-2.5"></textarea>
                </div>

                <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-100">
                    <button type="button" wire:click="$set('showAdjustmentModal', false)" class="btn-outline">Cancel</button>
                    <button type="submit" class="btn-glow">Update & Log Ledger</button>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>
