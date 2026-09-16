<?php

namespace App\Livewire\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Livewire\Component;
use Livewire\WithPagination;

class Inventory extends Component
{
    use WithPagination;

    public string $search = '';
    public string $selectedCategory = '';
    public string $stockFilter = ''; // low_stock, out_of_stock

    // Product Modal
    public bool $showProductModal = false;
    public ?int $editingProductId = null;
    public string $barcode = '';
    public string $name = '';
    public ?int $categoryId = null;
    public float $wholesaleCost = 0.00;
    public float $retailPrice = 0.00;
    public int $stockQuantity = 0;
    public int $minimumStockLevel = 5;
    public string $unit = '1 unit';
    public string $brand = '';

    // Stock Adjustment Modal
    public bool $showAdjustmentModal = false;
    public ?int $adjustProductId = null;
    public ?string $adjustProductName = null;
    public int $currentStock = 0;
    public int $newStockQuantity = 0;
    public string $adjustmentReason = '';

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
        $this->name = $p->name;
        $this->categoryId = $p->category_id;
        $this->wholesaleCost = (float) $p->wholesale_cost;
        $this->retailPrice = (float) $p->retail_price;
        $this->stockQuantity = $p->stock_quantity;
        $this->minimumStockLevel = $p->minimum_stock_level;
        $this->unit = $p->unit;
        $this->brand = $p->brand ?? '';
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
        ]);

        if ($this->editingProductId) {
            $p = Product::findOrFail($this->editingProductId);
            $p->update([
                'barcode' => $this->barcode,
                'name' => $this->name,
                'category_id' => $this->categoryId,
                'wholesale_cost' => $this->wholesaleCost,
                'retail_price' => $this->retailPrice,
                'stock_quantity' => $this->stockQuantity,
                'minimum_stock_level' => $this->minimumStockLevel,
                'unit' => $this->unit,
                'brand' => $this->brand,
            ]);
            session()->flash('message', "Product '{$p->name}' updated successfully.");
        } else {
            $p = Product::create([
                'barcode' => $this->barcode,
                'name' => $this->name,
                'category_id' => $this->categoryId,
                'wholesale_cost' => $this->wholesaleCost,
                'retail_price' => $this->retailPrice,
                'stock_quantity' => $this->stockQuantity,
                'minimum_stock_level' => $this->minimumStockLevel,
                'unit' => $this->unit,
                'brand' => $this->brand,
                'status' => 'active',
            ]);

            StockMovement::create([
                'product_id' => $p->id,
                'type' => 'Purchase',
                'quantity' => $p->stock_quantity,
                'stock_before' => 0,
                'stock_after' => $p->stock_quantity,
                'reference_type' => 'Receiving',
                'reason' => 'Initial Inventory Setup',
                'created_by' => auth()->user()?->name ?? 'Admin',
            ]);
            session()->flash('message', "Product '{$p->name}' created successfully.");
        }

        $this->showProductModal = false;
        $this->resetProductForm();
    }

    public function openAdjustmentModal(int $id)
    {
        $p = Product::findOrFail($id);
        $this->adjustProductId = $p->id;
        $this->adjustProductName = $p->name;
        $this->currentStock = $p->stock_quantity;
        $this->newStockQuantity = $p->stock_quantity;
        $this->adjustmentReason = '';
        $this->showAdjustmentModal = true;
    }

    public function saveAdjustment(InventoryService $inventoryService)
    {
        $this->validate([
            'newStockQuantity' => 'required|integer|min:0',
            'adjustmentReason' => 'required|string|min:3',
        ]);

        try {
            $p = $inventoryService->adjustStock(
                $this->adjustProductId,
                $this->newStockQuantity,
                $this->adjustmentReason,
                'Manual Adjustment',
                auth()->user()?->name ?? 'Admin'
            );

            session()->flash('message', "Stock for '{$p->name}' adjusted to {$p->stock_quantity}. Ledger updated.");
            $this->showAdjustmentModal = false;
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    private function resetProductForm()
    {
        $this->editingProductId = null;
        $this->barcode = '';
        $this->name = '';
        $this->categoryId = Category::first()?->id;
        $this->wholesaleCost = 0.00;
        $this->retailPrice = 0.00;
        $this->stockQuantity = 0;
        $this->minimumStockLevel = 5;
        $this->unit = '1 unit';
        $this->brand = '';
    }

    public function render()
    {
        $query = Product::with('category')->orderBy('id', 'desc');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('barcode', 'like', "%{$this->search}%")
                    ->orWhere('brand', 'like', "%{$this->search}%");
            });
        }

        if ($this->selectedCategory) {
            $query->where('category_id', $this->selectedCategory);
        }

        if ($this->stockFilter === 'low_stock') {
            $query->whereRaw('stock_quantity <= minimum_stock_level AND stock_quantity > 0');
        } elseif ($this->stockFilter === 'out_of_stock') {
            $query->where('stock_quantity', '<=', 0);
        }

        return view('livewire.admin.inventory', [
            'products' => $query->paginate(15),
            'categories' => Category::where('status', 'active')->orderBy('name')->get(),
            'recentMovements' => StockMovement::with('product')->orderBy('id', 'desc')->limit(10)->get(),
        ])->layout('components.layouts.app', ['title' => 'Baqqala Admin — Products & Inventory Ledger']);
    }
}
