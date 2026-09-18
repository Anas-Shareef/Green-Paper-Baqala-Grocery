<?php

namespace App\Livewire\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\StockReceiptItem;
use App\Models\Supplier;
use App\Services\InventoryService;
use Livewire\Component;

class StockReceiving extends Component
{
    // Mode
    public string $viewMode = 'station'; // station or history

    // Session State
    public ?int $currentReceiptId = null;
    public string $grnNumber = 'GRN-NEW';
    public ?int $supplierId = null;
    public string $supplierInvoiceNumber = '';
    public string $invoiceDate = '';
    public string $purchaseReference = '';
    public string $receivingDate = '';
    public string $status = 'draft';
    public float $discount = 0.00;
    public float $otherCharges = 0.00;
    public string $paymentStatus = 'unpaid';
    public string $notes = '';

    // Items list
    public array $items = [];

    // Scanner
    public string $barcodeInput = '';
    public string $scannerStatus = 'READY';
    public string $lastScannedBarcode = '';
    public ?int $flashProductId = null;

    // Modals
    public bool $showUnknownModal = false;
    public string $unknownBarcode = '';
    public string $newProductName = '';
    public float $newRetailPrice = 0.00;
    public float $newWholesaleCost = 0.00;
    public string $newUnit = 'piece';

    public bool $showSupplierModal = false;
    public string $newSupplierName = '';
    public string $newSupplierPhone = '';
    public string $newSupplierTax = '';

    public bool $showConfirmModal = false;

    // View GRN details
    public ?StockReceipt $viewingReceipt = null;

    public function mount()
    {
        $this->invoiceDate = date('Y-m-d');
        $this->receivingDate = date('Y-m-d');
    }

    public function scanBarcode()
    {
        $code = trim($this->barcodeInput);
        $this->barcodeInput = '';

        if (empty($code)) {
            return;
        }

        $this->lastScannedBarcode = $code;

        // Check if barcode already exists in current items
        foreach ($this->items as $index => $item) {
            if ($item['barcode'] === $code) {
                // REPEATED SCAN: increment in place!
                $this->items[$index]['quantity_received'] += 1;
                $this->items[$index]['quantity_sellable'] = max(0, $this->items[$index]['quantity_received'] - $this->items[$index]['quantity_damaged']);
                $this->recalculateLine($index);
                $this->scannerStatus = 'INCREMENTED';
                $this->flashProductId = $item['product_id'];
                return;
            }
        }

        // Lookup product by barcode
        $product = Product::where('barcode', $code)->first();

        if ($product) {
            $unitCost = (float) $product->wholesale_cost;
            $taxAmt = round($unitCost * 0.05, 2);

            $this->items[] = [
                'product_id' => $product->id,
                'barcode' => $product->barcode,
                'product_name' => $product->name,
                'sku' => $product->sku ?? ('SKU-' . $product->id),
                'unit' => $product->unit ?? 'piece',
                'quantity_expected' => 1,
                'quantity_received' => 1,
                'quantity_damaged' => 0,
                'quantity_sellable' => 1,
                'unit_cost' => $unitCost,
                'discount' => 0.00,
                'tax_amount' => $taxAmt,
                'subtotal' => round($unitCost + $taxAmt, 2),
                'expiry_date' => $product->expiry_date ? $product->expiry_date->format('Y-m-d') : '',
                'batch_number' => '',
                'notes' => '',
            ];

            $this->scannerStatus = 'FOUND';
            $this->flashProductId = $product->id;
        } else {
            $this->scannerStatus = 'NOT_FOUND';
            $this->unknownBarcode = $code;
            $this->showUnknownModal = true;
        }
    }

    public function recalculateLine(int $index)
    {
        if (!isset($this->items[$index])) return;

        $item = &$this->items[$index];
        $qty = (int) $item['quantity_received'];
        $damaged = (int) $item['quantity_damaged'];
        $item['quantity_sellable'] = max(0, $qty - $damaged);

        $cost = (float) $item['unit_cost'];
        $disc = (float) $item['discount'];
        $tax = (float) $item['tax_amount'];

        $item['subtotal'] = round(max(0, ($qty * $cost) - $disc + $tax), 2);
    }

    public function removeItem(int $index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function saveDraft(InventoryService $inventoryService)
    {
        if (empty($this->items)) {
            session()->flash('error', 'Please scan or add at least one product before saving.');
            return;
        }

        try {
            $header = [
                'supplier_id' => $this->supplierId,
                'supplier_invoice_number' => $this->supplierInvoiceNumber,
                'invoice_date' => $this->invoiceDate,
                'purchase_reference' => $this->purchaseReference,
                'receiving_date' => $this->receivingDate,
                'status' => 'draft',
                'discount' => $this->discount,
                'other_charges' => $this->otherCharges,
                'payment_status' => $this->paymentStatus,
                'notes' => $this->notes,
            ];

            if ($this->currentReceiptId) {
                $receipt = StockReceipt::findOrFail($this->currentReceiptId);
                $receipt = $inventoryService->updateStockReceipt($receipt, $header, $this->items);
            } else {
                $receipt = $inventoryService->createStockReceipt($header, $this->items, auth()->user()?->name ?? 'Admin');
                $this->currentReceiptId = $receipt->id;
                $this->grnNumber = $receipt->grn_number;
            }

            session()->flash('message', "Draft {$receipt->grn_number} saved. Stock remains unchanged until confirmed.");
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function confirmReceipt(InventoryService $inventoryService)
    {
        if (empty($this->items)) {
            session()->flash('error', 'Cannot confirm an empty receipt.');
            return;
        }

        try {
            if (!$this->currentReceiptId) {
                $header = [
                    'supplier_id' => $this->supplierId,
                    'supplier_invoice_number' => $this->supplierInvoiceNumber,
                    'invoice_date' => $this->invoiceDate,
                    'purchase_reference' => $this->purchaseReference,
                    'receiving_date' => $this->receivingDate,
                    'status' => 'draft',
                    'discount' => $this->discount,
                    'other_charges' => $this->otherCharges,
                    'payment_status' => $this->paymentStatus,
                    'notes' => $this->notes,
                ];
                $receipt = $inventoryService->createStockReceipt($header, $this->items, auth()->user()?->name ?? 'Admin');
                $this->currentReceiptId = $receipt->id;
            }

            $confirmed = $inventoryService->confirmStockReceipt($this->currentReceiptId, auth()->user()?->name ?? 'Admin');
            $this->status = 'received';
            $this->showConfirmModal = false;

            session()->flash('message', "GRN {$confirmed->grn_number} successfully confirmed! Received stock committed to physical inventory.");
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function createUnknownProduct()
    {
        $this->validate([
            'unknownBarcode' => 'required|string|unique:products,barcode',
            'newProductName' => 'required|string|max:255',
            'newWholesaleCost' => 'required|numeric|min:0',
            'newRetailPrice' => 'required|numeric|min:0',
        ]);

        $product = Product::create([
            'barcode' => $this->unknownBarcode,
            'name' => $this->newProductName,
            'category_id' => Category::first()?->id ?? 1,
            'unit' => $this->newUnit,
            'wholesale_cost' => $this->newWholesaleCost,
            'retail_price' => $this->newRetailPrice,
            'stock_quantity' => 0,
            'minimum_stock_level' => 5,
            'status' => 'active',
        ]);

        $taxAmt = round($this->newWholesaleCost * 0.05, 2);
        $this->items[] = [
            'product_id' => $product->id,
            'barcode' => $product->barcode,
            'product_name' => $product->name,
            'sku' => $product->sku ?? ('SKU-' . $product->id),
            'unit' => $product->unit,
            'quantity_expected' => 1,
            'quantity_received' => 1,
            'quantity_damaged' => 0,
            'quantity_sellable' => 1,
            'unit_cost' => $this->newWholesaleCost,
            'discount' => 0.00,
            'tax_amount' => $taxAmt,
            'subtotal' => round($this->newWholesaleCost + $taxAmt, 2),
            'expiry_date' => '',
            'batch_number' => '',
            'notes' => '',
        ];

        $this->showUnknownModal = false;
        $this->newProductName = '';
        $this->newWholesaleCost = 0.00;
        $this->newRetailPrice = 0.00;

        session()->flash('message', "Product '{$product->name}' created and added to receiving cart.");
    }

    public function createQuickSupplier()
    {
        $this->validate([
            'newSupplierName' => 'required|string|max:255|unique:suppliers,name',
        ]);

        $supplier = Supplier::create([
            'name' => $this->newSupplierName,
            'phone' => $this->newSupplierPhone,
            'tax_number' => $this->newSupplierTax,
            'status' => 'active',
        ]);

        $this->supplierId = $supplier->id;
        $this->showSupplierModal = false;
        $this->newSupplierName = '';
        $this->newSupplierPhone = '';
        $this->newSupplierTax = '';

        session()->flash('message', "Supplier '{$supplier->name}' created.");
    }

    public function resetSession()
    {
        $this->currentReceiptId = null;
        $this->grnNumber = 'GRN-NEW';
        $this->status = 'draft';
        $this->supplierId = null;
        $this->supplierInvoiceNumber = '';
        $this->purchaseReference = '';
        $this->notes = '';
        $this->discount = 0.00;
        $this->otherCharges = 0.00;
        $this->items = [];
    }

    public function render(InventoryService $inventoryService)
    {
        $kpis = $inventoryService->getReceivingKPIs();
        $suppliers = Supplier::orderBy('name')->get();
        $history = StockReceipt::with(['supplier', 'items'])->orderBy('id', 'desc')->paginate(15);

        return view('livewire.admin.stock-receiving', [
            'kpis' => $kpis,
            'suppliers' => $suppliers,
            'history' => $history,
        ])->layout('components.layouts.app', ['title' => 'Baqqala Admin — Stock Receiving Station']);
    }
}
