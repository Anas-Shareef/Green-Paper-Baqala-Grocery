<?php

namespace App\Livewire\Admin;

use App\Models\Product;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Livewire\Component;

class StockReceiving extends Component
{
    public string $barcodeInput = '';
    public ?int $selectedProductId = null;
    public ?Product $selectedProduct = null;
    public int $receivingQuantity = 10;
    public string $reason = 'Stock Purchase Receiving';

    public bool $showUnknownModal = false;
    public string $newBarcode = '';
    public string $newName = '';
    public float $newRetailPrice = 0.00;
    public float $newWholesaleCost = 0.00;

    public function scanBarcode()
    {
        $barcode = trim($this->barcodeInput);
        $this->barcodeInput = '';

        if (empty($barcode)) {
            return;
        }

        $product = Product::where('barcode', $barcode)->first();

        if ($product) {
            $this->selectedProductId = $product->id;
            $this->selectedProduct = $product;
            session()->flash('info', "Product '{$product->name}' scanned. Enter received quantity below.");
        } else {
            $this->newBarcode = $barcode;
            $this->showUnknownModal = true;
        }
    }

    public function selectProduct(int $id)
    {
        $product = Product::findOrFail($id);
        $this->selectedProductId = $product->id;
        $this->selectedProduct = $product;
    }

    public function submitReceiving(InventoryService $inventoryService)
    {
        if (!$this->selectedProduct) {
            session()->flash('error', "Please select or scan a product first.");
            return;
        }

        $this->validate([
            'receivingQuantity' => 'required|integer|min:1',
            'reason' => 'required|string|min:3',
        ]);

        try {
            $product = $inventoryService->receiveStock(
                $this->selectedProduct->id,
                $this->receivingQuantity,
                $this->reason,
                auth()->user()?->name ?? 'Admin'
            );

            session()->flash('message', "Successfully received +{$this->receivingQuantity} units of '{$product->name}'. New Stock: {$product->stock_quantity}.");
            $this->selectedProduct = Product::find($product->id);
            $this->receivingQuantity = 10;
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function saveUnknownProduct()
    {
        $this->validate([
            'newBarcode' => 'required|string',
            'newName' => 'required|string|max:255',
            'newRetailPrice' => 'required|numeric|min:0',
            'newWholesaleCost' => 'required|numeric|min:0',
            'receivingQuantity' => 'required|integer|min:1',
        ]);

        $product = Product::create([
            'barcode' => $this->newBarcode,
            'name' => $this->newName,
            'category_id' => \App\Models\Category::first()?->id ?? 1,
            'retail_price' => $this->newRetailPrice,
            'wholesale_cost' => $this->newWholesaleCost,
            'stock_quantity' => $this->receivingQuantity,
            'status' => 'active',
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'type' => 'Purchase',
            'quantity' => $this->receivingQuantity,
            'stock_before' => 0,
            'stock_after' => $this->receivingQuantity,
            'reference_type' => 'Receiving',
            'reason' => $this->reason,
            'created_by' => auth()->user()?->name ?? 'Admin',
        ]);

        $this->showUnknownModal = false;
        $this->selectedProductId = $product->id;
        $this->selectedProduct = $product;
        session()->flash('message', "New product '{$product->name}' created with initial received stock of {$this->receivingQuantity}.");
    }

    public function render()
    {
        return view('livewire.admin.stock-receiving', [
            'recentReceivings' => StockMovement::with('product')
                ->where('type', 'Purchase')
                ->orderBy('id', 'desc')
                ->limit(15)
                ->get(),
            'productsList' => Product::where('status', 'active')->orderBy('name')->get(),
        ])->layout('components.layouts.app', ['title' => 'Baqqala Admin — Stock Receiving Workflow']);
    }
}
