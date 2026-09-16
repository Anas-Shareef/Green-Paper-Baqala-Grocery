<?php

namespace App\Livewire\Pos;

use App\Models\Category;
use App\Models\Product;
use App\Services\OrderService;
use Livewire\Component;

class PosScreen extends Component
{
    public string $barcodeInput = '';
    public array $cart = []; // ['product_id' => int, 'name' => string, 'barcode' => string, 'retail_price' => float, 'wholesale_cost' => float, 'quantity' => int, 'stock_quantity' => int, 'unit' => string, 'image' => string]
    public float $discountAmount = 0.00;
    public string $paymentMethod = 'Cash';

    // Rapid Product Creation Modal
    public bool $showNewProductModal = false;
    public string $newBarcode = '';
    public string $newName = '';
    public ?int $newCategoryId = null;
    public float $newRetailPrice = 0.00;
    public float $newWholesaleCost = 0.00;
    public int $newInitialStock = 10;
    public string $newUnit = '1 unit';

    // Receipt Modal
    public bool $showReceiptModal = false;
    public ?object $lastCompletedOrder = null;

    // Toast Notification
    public ?string $toastMessage = null;
    public string $toastType = 'success'; // success, error, warning

    protected $listeners = ['focus-scanner' => '$refresh'];

    public function mount()
    {
        $this->newCategoryId = Category::first()?->id;
    }

    /**
     * Handle barcode scan (Keydown Enter on scanner input).
     */
    public function scanBarcode()
    {
        $barcode = trim($this->barcodeInput);
        $this->barcodeInput = '';

        if (empty($barcode)) {
            return;
        }

        $product = Product::where('barcode', $barcode)->first();

        if ($product) {
            $this->addToCart($product);
        } else {
            // Product not found -> Open Rapid Product Creation Modal
            $this->newBarcode = $barcode;
            $this->newName = '';
            $this->newRetailPrice = 0.00;
            $this->newWholesaleCost = 0.00;
            $this->newInitialStock = 10;
            $this->showNewProductModal = true;
            $this->showToast("Barcode '{$barcode}' not found. Create new product below.", 'warning');
        }
    }

    public function addToCart(Product $product)
    {
        if ($product->stock_quantity <= 0) {
            $this->showToast("{$product->name} is OUT OF STOCK!", 'error');
            return;
        }

        $existingIndex = null;
        foreach ($this->cart as $index => $item) {
            if ($item['product_id'] === $product->id) {
                $existingIndex = $index;
                break;
            }
        }

        if ($existingIndex !== null) {
            $currentQty = $this->cart[$existingIndex]['quantity'];
            if ($currentQty + 1 > $product->stock_quantity) {
                $this->showToast("Cannot add more. Stock limit ({$product->stock_quantity}) reached for {$product->name}.", 'warning');
                return;
            }
            $this->cart[$existingIndex]['quantity'] += 1;
        } else {
            $this->cart[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'barcode' => $product->barcode,
                'unit' => $product->unit,
                'retail_price' => (float) $product->retail_price,
                'wholesale_cost' => (float) $product->wholesale_cost,
                'quantity' => 1,
                'stock_quantity' => (int) $product->stock_quantity,
                'image' => $product->image ?? 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=200&q=80',
            ];
        }

        $this->showToast("Scanned: {$product->name}", 'success');
    }

    public function incrementQty(int $index)
    {
        if (isset($this->cart[$index])) {
            $item = $this->cart[$index];
            if ($item['quantity'] + 1 > $item['stock_quantity']) {
                $this->showToast("Stock limit reached ({$item['stock_quantity']})", 'warning');
                return;
            }
            $this->cart[$index]['quantity'] += 1;
        }
    }

    public function decrementQty(int $index)
    {
        if (isset($this->cart[$index])) {
            if ($this->cart[$index]['quantity'] > 1) {
                $this->cart[$index]['quantity'] -= 1;
            } else {
                $this->removeItem($index);
            }
        }
    }

    public function removeItem(int $index)
    {
        if (isset($this->cart[$index])) {
            unset($this->cart[$index]);
            $this->cart = array_values($this->cart);
        }
    }

    public function clearCart()
    {
        $this->cart = [];
        $this->discountAmount = 0.00;
        $this->barcodeInput = '';
    }

    /**
     * Save new product rapidly and auto-add to current cart.
     */
    public function saveRapidProduct()
    {
        $this->validate([
            'newBarcode' => 'required|string',
            'newName' => 'required|string|max:255',
            'newCategoryId' => 'required|exists:categories,id',
            'newRetailPrice' => 'required|numeric|min:0',
            'newWholesaleCost' => 'required|numeric|min:0',
            'newInitialStock' => 'required|integer|min:1',
        ]);

        $product = Product::create([
            'barcode' => $this->newBarcode,
            'name' => $this->newName,
            'category_id' => $this->newCategoryId,
            'retail_price' => $this->newRetailPrice,
            'wholesale_cost' => $this->newWholesaleCost,
            'stock_quantity' => $this->newInitialStock,
            'unit' => $this->newUnit,
            'status' => 'active',
        ]);

        // Record initial stock movement
        \App\Models\StockMovement::create([
            'product_id' => $product->id,
            'type' => 'Purchase',
            'quantity' => $this->newInitialStock,
            'stock_before' => 0,
            'stock_after' => $this->newInitialStock,
            'reference_type' => 'Receiving',
            'reason' => 'Rapid Product POS Creation',
            'created_by' => auth()->user()?->name ?? 'POS Cashier',
        ]);

        $this->showNewProductModal = false;
        $this->addToCart($product);
        $this->showToast("Product '{$product->name}' created and added to cart!", 'success');
    }

    /**
     * Complete POS Walk-in Cash/Card Sale.
     */
    public function completeSale(OrderService $orderService)
    {
        if (empty($this->cart)) {
            $this->showToast("Cannot complete sale: Cart is empty!", 'error');
            return;
        }

        try {
            $items = array_map(function ($item) {
                return [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ];
            }, $this->cart);

            $order = $orderService->createOrder($items, [
                'order_source' => 'POS',
                'payment_method' => $this->paymentMethod,
                'discount_amount' => $this->discountAmount,
                'internal_delivery_cost' => 0.00,
                'user_name' => auth()->user()?->name ?? 'POS Cashier',
            ]);

            $order->load('items');
            $this->lastCompletedOrder = $order;
            $this->showReceiptModal = true;
            $this->clearCart();
            $this->showToast("Sale completed successfully! Order #{$order->order_number}", 'success');
        } catch (\Exception $e) {
            $this->showToast($e->getMessage(), 'error');
        }
    }

    public function getSubtotalProperty(): float
    {
        return array_reduce($this->cart, function ($carry, $item) {
            return $carry + ($item['retail_price'] * $item['quantity']);
        }, 0.00);
    }

    public function getTotalProperty(): float
    {
        return max(0, $this->subtotal - $this->discountAmount);
    }

    public function getEstimatedProfitProperty(): float
    {
        $cost = array_reduce($this->cart, function ($carry, $item) {
            return $carry + ($item['wholesale_cost'] * $item['quantity']);
        }, 0.00);

        return $this->total - $cost;
    }

    private function showToast(string $message, string $type = 'success')
    {
        $this->toastMessage = $message;
        $this->toastType = $type;
    }

    public function render()
    {
        return view('livewire.pos.pos-screen', [
            'categories' => Category::where('status', 'active')->orderBy('name')->get(),
            'quickProducts' => Product::where('status', 'active')->where('stock_quantity', '>', 0)->orderBy('id', 'desc')->limit(12)->get(),
        ])->layout('components.layouts.app', ['title' => 'Baqqala — Keyboard Barcode POS']);
    }
}
