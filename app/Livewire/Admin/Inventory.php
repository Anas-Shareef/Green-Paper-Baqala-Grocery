<?php

namespace App\Livewire\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class Inventory extends Component
{
    use WithPagination;

    // Navigation Tabs: stock, movements, counts, reorder, valuation
    public string $activeTab = 'stock';

    // Filters & Search
    public string $search = '';
    public string $selectedCategory = '';
    public string $stockFilter = ''; // all, in_stock, low_stock, out_of_stock, negative, overstocked
    public string $expiryFilter = ''; // all, expired, within_3_days, within_7_days, within_30_days
    public string $sortBy = 'urgency'; // urgency, name, stock_asc, stock_desc, cost_desc, retail_desc

    // Slide-over Product Detail Drawer
    public ?int $drawerProductId = null;
    public ?Product $drawerProduct = null;

    // Product Create / Edit Modal
    public bool $showProductModal = false;
    public ?int $editingProductId = null;
    public string $barcode = '';
    public string $sku = '';
    public string $name = '';
    public ?int $categoryId = null;
    public float $wholesaleCost = 0.00;
    public float $retailPrice = 0.00;
    public int $stockQuantity = 0;
    public int $minimumStockLevel = 5;
    public ?int $maximumStockLevel = 50;
    public string $unit = '1 unit';
    public string $brand = '';
    public ?string $expiryDate = null;
    public ?string $supplierName = null;

    // Stock Adjustment Modal
    public bool $showAdjustmentModal = false;
    public ?int $adjustProductId = null;
    public ?string $adjustProductName = null;
    public int $adjustCurrentStock = 0;
    public int $adjustNewQuantity = 0;
    public string $adjustType = 'Damage'; // Damage, Expiry, Correction, Lost, Found
    public string $adjustReason = '';
    public string $adjustNotes = '';

    // Physical Stock Count State
    public bool $showNewCountModal = false;
    public ?int $countCategoryId = null;
    public string $countNotes = '';
    public ?int $activeCountId = null;

    protected $queryString = [
        'activeTab' => ['except' => 'stock'],
        'search' => ['except' => ''],
        'selectedCategory' => ['except' => ''],
        'stockFilter' => ['except' => ''],
    ];

    public function mount()
    {
        // Auto-reconcile opening balances if any products lack ledger history
        app(InventoryService::class)->reconcileOpeningStock();
    }

    public function setTab(string $tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedSelectedCategory()
    {
        $this->resetPage();
    }

    public function updatedStockFilter()
    {
        $this->resetPage();
    }

    public function updatedExpiryFilter()
    {
        $this->resetPage();
    }

    // ==========================================
    // PRODUCT DETAIL DRAWER
    // ==========================================

    public function openDrawer(int $productId)
    {
        $this->drawerProductId = $productId;
        $this->drawerProduct = Product::with(['category', 'stockMovements' => function ($q) {
            $q->orderBy('id', 'desc')->limit(20);
        }])->findOrFail($productId);
    }

    public function closeDrawer()
    {
        $this->drawerProductId = null;
        $this->drawerProduct = null;
    }

    // ==========================================
    // STOCK ADJUSTMENT MODAL
    // ==========================================

    public function openAdjustmentModal(int $productId)
    {
        $product = Product::findOrFail($productId);
        $this->adjustProductId = $product->id;
        $this->adjustProductName = $product->name;
        $this->adjustCurrentStock = $product->stock_quantity;
        $this->adjustNewQuantity = $product->stock_quantity;
        $this->adjustType = 'Damage';
        $this->adjustReason = '';
        $this->adjustNotes = '';
        $this->showAdjustmentModal = true;
    }

    public function saveAdjustment(InventoryService $inventoryService)
    {
        $this->validate([
            'adjustNewQuantity' => 'required|integer|min:0',
            'adjustReason' => 'required|string|min:3',
            'adjustType' => 'required|string',
        ]);

        try {
            $product = $inventoryService->adjustStock(
                $this->adjustProductId,
                $this->adjustNewQuantity,
                $this->adjustReason,
                $this->adjustType,
                auth()->user()?->name ?? 'Admin',
                $this->adjustNotes
            );

            session()->flash('message', "Stock for '{$product->name}' adjusted to {$product->stock_quantity} units. Ledger movement logged.");
            $this->showAdjustmentModal = false;

            if ($this->drawerProductId === $this->adjustProductId) {
                $this->openDrawer($this->adjustProductId);
            }
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    // ==========================================
    // PRODUCT CREATE / EDIT MODAL
    // ==========================================

    public function createProduct()
    {
        $this->resetProductForm();
        $this->showProductModal = true;
    }

    public function editProduct(int $id)
    {
        $p = Product::findOrFail($id);
        $this->editingProductId = $p->id;
        $this->barcode = $p->barcode;
        $this->sku = $p->sku ?? '';
        $this->name = $p->name;
        $this->categoryId = $p->category_id;
        $this->wholesaleCost = (float) $p->wholesale_cost;
        $this->retailPrice = (float) $p->retail_price;
        $this->stockQuantity = $p->stock_quantity;
        $this->minimumStockLevel = $p->minimum_stock_level;
        $this->maximumStockLevel = $p->maximum_stock_level ?? 50;
        $this->unit = $p->unit;
        $this->brand = $p->brand ?? '';
        $this->expiryDate = $p->expiry_date ? $p->expiry_date->toDateString() : null;
        $this->supplierName = $p->supplier_name ?? '';
        $this->showProductModal = true;
    }

    public function saveProduct()
    {
        $this->validate([
            'barcode' => 'required|string',
            'name' => 'required|string|max:255',
            'categoryId' => 'required|exists:categories,id',
            'wholesaleCost' => 'required|numeric|min:0',
            'retailPrice' => 'required|numeric|min:0',
            'stockQuantity' => 'required|integer|min:0',
            'minimumStockLevel' => 'required|integer|min:0',
            'expiryDate' => 'nullable|date',
        ]);

        if ($this->editingProductId) {
            $p = Product::findOrFail($this->editingProductId);
            $p->update([
                'barcode' => $this->barcode,
                'sku' => $this->sku ?: null,
                'name' => $this->name,
                'category_id' => $this->categoryId,
                'wholesale_cost' => $this->wholesaleCost,
                'retail_price' => $this->retailPrice,
                'minimum_stock_level' => $this->minimumStockLevel,
                'maximum_stock_level' => $this->maximumStockLevel,
                'unit' => $this->unit,
                'brand' => $this->brand ?: null,
                'expiry_date' => $this->expiryDate ?: null,
                'supplier_name' => $this->supplierName ?: null,
            ]);
            session()->flash('message', "Product '{$p->name}' updated successfully.");
        } else {
            $p = Product::create([
                'barcode' => $this->barcode,
                'sku' => $this->sku ?: null,
                'name' => $this->name,
                'category_id' => $this->categoryId,
                'wholesale_cost' => $this->wholesaleCost,
                'retail_price' => $this->retailPrice,
                'stock_quantity' => $this->stockQuantity,
                'minimum_stock_level' => $this->minimumStockLevel,
                'maximum_stock_level' => $this->maximumStockLevel,
                'unit' => $this->unit,
                'brand' => $this->brand ?: null,
                'expiry_date' => $this->expiryDate ?: null,
                'supplier_name' => $this->supplierName ?: null,
                'status' => 'active',
            ]);

            StockMovement::create([
                'product_id' => $p->id,
                'type' => 'Opening Stock',
                'quantity' => $p->stock_quantity,
                'stock_before' => 0,
                'stock_after' => $p->stock_quantity,
                'unit_cost' => $p->wholesale_cost,
                'reference_type' => 'Opening',
                'reason' => 'Initial Inventory Setup',
                'created_by' => auth()->user()?->name ?? 'Admin',
            ]);
            session()->flash('message', "Product '{$p->name}' created with initial stock.");
        }

        $this->showProductModal = false;
        $this->resetProductForm();
    }

    private function resetProductForm()
    {
        $this->editingProductId = null;
        $this->barcode = '';
        $this->sku = '';
        $this->name = '';
        $this->categoryId = Category::first()?->id;
        $this->wholesaleCost = 0.00;
        $this->retailPrice = 0.00;
        $this->stockQuantity = 0;
        $this->minimumStockLevel = 5;
        $this->maximumStockLevel = 50;
        $this->unit = '1 unit';
        $this->brand = '';
        $this->expiryDate = null;
        $this->supplierName = '';
    }

    // ==========================================
    // PHYSICAL STOCK COUNT SESSIONS
    // ==========================================

    public function startNewCount(InventoryService $inventoryService)
    {
        $count = $inventoryService->startStockCount(
            $this->countCategoryId ?: null,
            $this->countNotes ?: 'Routine Physical Inventory Count',
            auth()->user()?->name ?? 'Admin'
        );

        $this->showNewCountModal = false;
        $this->activeCountId = $count->id;
        $this->countNotes = '';
        $this->countCategoryId = null;
        session()->flash('message', "Stock Count session #{$count->count_number} initialized with {$count->total_items} products.");
    }

    public function viewCountSession(int $countId)
    {
        $this->activeCountId = $countId;
    }

    public function closeCountSession()
    {
        $this->activeCountId = null;
    }

    public function updateCountItemQuantity(int $itemId, int $qty, InventoryService $inventoryService)
    {
        try {
            $inventoryService->updateStockCountItem($itemId, max(0, $qty));
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function approveCountSession(int $countId, InventoryService $inventoryService)
    {
        try {
            $count = $inventoryService->approveStockCount($countId, auth()->user()?->name ?? 'Admin');
            session()->flash('message', "Stock Count #{$count->count_number} APPROVED. All variances have been corrected in the stock ledger.");
            $this->activeCountId = null;
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    // ==========================================
    // CSV EXPORT
    // ==========================================

    public function exportCsv()
    {
        $products = $this->buildStockQuery()->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="baqqala_inventory_' . date('Y-m-d_His') . '.csv"',
        ];

        $callback = function () use ($products) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Product Name',
                'Barcode',
                'SKU',
                'Category',
                'Wholesale Cost (AED)',
                'Retail Price (AED)',
                'Physical Stock',
                'Reserved Stock',
                'Available Stock',
                'Reorder Level',
                'Status',
                'Expiry Date',
                'Supplier',
            ]);

            foreach ($products as $p) {
                $available = max(0, $p->stock_quantity - $p->reserved_quantity);
                $status = $p->stock_quantity <= 0 ? 'Out of Stock' : ($available <= $p->minimum_stock_level ? 'Low Stock' : 'In Stock');

                fputcsv($handle, [
                    $p->id,
                    $p->name,
                    $p->barcode,
                    $p->sku ?? '',
                    $p->category?->name ?? 'Uncategorized',
                    number_format($p->wholesale_cost, 2),
                    number_format($p->retail_price, 2),
                    $p->stock_quantity,
                    $p->reserved_quantity,
                    $available,
                    $p->minimum_stock_level,
                    $status,
                    $p->expiry_date ? $p->expiry_date->toDateString() : '',
                    $p->supplier_name ?? '',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ==========================================
    // QUERY BUILDER
    // ==========================================

    private function buildStockQuery()
    {
        $query = Product::with('category')->where('status', 'active');

        if (!empty(trim($this->search))) {
            $term = trim($this->search);
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('brand', 'like', "%{$term}%");
            });
        }

        if (!empty($this->selectedCategory)) {
            $query->where('category_id', $this->selectedCategory);
        }

        if ($this->stockFilter === 'low_stock') {
            $query->whereRaw('(stock_quantity - reserved_quantity) <= minimum_stock_level AND (stock_quantity - reserved_quantity) > 0');
        } elseif ($this->stockFilter === 'out_of_stock') {
            $query->whereRaw('(stock_quantity - reserved_quantity) <= 0');
        } elseif ($this->stockFilter === 'in_stock') {
            $query->whereRaw('(stock_quantity - reserved_quantity) > minimum_stock_level');
        } elseif ($this->stockFilter === 'negative') {
            $query->where('stock_quantity', '<', 0);
        } elseif ($this->stockFilter === 'overstocked') {
            $query->whereRaw('maximum_stock_level IS NOT NULL AND stock_quantity > maximum_stock_level');
        }

        if ($this->expiryFilter === 'expired') {
            $query->whereNotNull('expiry_date')->where('expiry_date', '<', Carbon::today()->toDateString());
        } elseif ($this->expiryFilter === 'within_3_days') {
            $query->whereNotNull('expiry_date')->whereBetween('expiry_date', [Carbon::today()->toDateString(), Carbon::today()->addDays(3)->toDateString()]);
        } elseif ($this->expiryFilter === 'within_7_days') {
            $query->whereNotNull('expiry_date')->whereBetween('expiry_date', [Carbon::today()->toDateString(), Carbon::today()->addDays(7)->toDateString()]);
        } elseif ($this->expiryFilter === 'within_30_days') {
            $query->whereNotNull('expiry_date')->whereBetween('expiry_date', [Carbon::today()->toDateString(), Carbon::today()->addDays(30)->toDateString()]);
        }

        // Sorting
        switch ($this->sortBy) {
            case 'name':
                $query->orderBy('name', 'asc');
                break;
            case 'stock_asc':
                $query->orderBy('stock_quantity', 'asc');
                break;
            case 'stock_desc':
                $query->orderBy('stock_quantity', 'desc');
                break;
            case 'cost_desc':
                $query->orderBy('wholesale_cost', 'desc');
                break;
            case 'retail_desc':
                $query->orderBy('retail_price', 'desc');
                break;
            case 'urgency':
            default:
                $query->orderByRaw('(stock_quantity - reserved_quantity) ASC')->orderBy('id', 'desc');
                break;
        }

        return $query;
    }

    public function render(InventoryService $inventoryService)
    {
        $kpis = $inventoryService->getInventoryKPIs();
        $categories = Category::where('status', 'active')->orderBy('name')->get();

        // 1. Stock Tab Products
        $products = $this->buildStockQuery()->paginate(20);

        // 2. Movements Ledger Tab
        $movements = StockMovement::with('product')
            ->orderBy('id', 'desc')
            ->paginate(30);

        // 3. Stock Counts Tab
        $counts = StockCount::with(['category', 'items.product'])
            ->orderBy('id', 'desc')
            ->get();

        $activeCount = $this->activeCountId 
            ? StockCount::with(['category', 'items.product'])->find($this->activeCountId)
            : null;

        // 4. Reorder Suggestions Tab
        $reorderSuggestions = ($this->activeTab === 'reorder')
            ? $inventoryService->getReorderRecommendations(50)
            : collect();

        // 5. Valuation Breakdown Tab
        $categoryValuations = ($this->activeTab === 'valuation')
            ? $inventoryService->getValuationByCategory()
            : collect();

        return view('livewire.admin.inventory', [
            'kpis' => $kpis,
            'categories' => $categories,
            'products' => $products,
            'movements' => $movements,
            'counts' => $counts,
            'activeCount' => $activeCount,
            'reorderSuggestions' => $reorderSuggestions,
            'categoryValuations' => $categoryValuations,
        ])->layout('components.layouts.app', ['title' => 'Baqqala Admin — Inventory Control Center & Ledger']);
    }
}
