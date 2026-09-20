<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\StockReceiptItem;
use App\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Internal atomic movement logger.
     */
    public function recordMovement(
        Product $product,
        string $type,
        int $quantityDiff,
        string $reason,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?float $unitCost = null,
        ?string $userName = null
    ): StockMovement {
        $stockBefore = (int) $product->stock_quantity;
        $stockAfter = max(0, $stockBefore + $quantityDiff);

        $product->update([
            'stock_quantity' => $stockAfter,
        ]);

        return StockMovement::create([
            'product_id' => $product->id,
            'type' => $type,
            'quantity' => $quantityDiff,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'unit_cost' => $unitCost ?? (float) $product->wholesale_cost,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reason' => $reason,
            'created_by' => $userName ?? auth()->user()?->name ?? 'Admin',
        ]);
    }

    /**
     * Record stock receiving/purchase with weighted-average cost updating.
     */
    public function receiveStock(
        int $productId,
        int $quantity,
        ?string $reason = 'Stock Purchase Receiving',
        ?float $unitCost = null,
        ?string $supplier = null,
        ?string $userName = null
    ): Product {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException("Receiving quantity must be greater than zero.");
        }

        return DB::transaction(function () use ($productId, $quantity, $reason, $unitCost, $supplier, $userName) {
            $product = Product::lockForUpdate()->findOrFail($productId);
            $stockBefore = (int) $product->stock_quantity;
            $stockAfter = $stockBefore + $quantity;

            // Weighted Average Cost recalculation
            $effectiveCost = $unitCost ?? (float) $product->wholesale_cost;
            if ($unitCost !== null && $stockAfter > 0) {
                $currentTotalCost = $stockBefore * (float) $product->wholesale_cost;
                $newIncomingCost = $quantity * $unitCost;
                $newAvgCost = round(($currentTotalCost + $newIncomingCost) / $stockAfter, 2);
                $product->wholesale_cost = $newAvgCost;
            }

            if (!empty($supplier)) {
                $product->supplier_name = $supplier;
            }

            $product->stock_quantity = $stockAfter;
            $product->save();

            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'Purchase',
                'quantity' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'unit_cost' => $effectiveCost,
                'reference_type' => 'Receiving',
                'reason' => $reason,
                'created_by' => $userName ?? auth()->user()?->name ?? 'Admin',
            ]);

            return $product;
        });
    }

    /**
     * Adjust product stock manually with mandatory reason and movement type.
     */
    public function adjustStock(
        int $productId,
        int $newStockQuantity,
        string $reason,
        string $type = 'Correction',
        ?string $userName = null,
        ?string $notes = null
    ): Product {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException("A mandatory reason must be provided for stock adjustment.");
        }

        if ($newStockQuantity < 0) {
            throw new \InvalidArgumentException("Stock quantity cannot become negative (Rule 1).");
        }

        return DB::transaction(function () use ($productId, $newStockQuantity, $reason, $type, $userName, $notes) {
            $product = Product::lockForUpdate()->findOrFail($productId);
            $stockBefore = (int) $product->stock_quantity;
            $reserved = (int) $product->reserved_quantity;

            if ($newStockQuantity < $reserved) {
                throw new \InvalidArgumentException("Cannot adjust stock to {$newStockQuantity} units because {$reserved} units are currently reserved by active orders (Rule 2).");
            }

            $diff = $newStockQuantity - $stockBefore;
            if ($diff === 0) {
                return $product;
            }

            // Standardize canonical movement type
            $canonicalType = match (strtolower($type)) {
                'damage', 'damaged' => StockMovement::TYPE_DAMAGED,
                'expiry', 'expired' => StockMovement::TYPE_EXPIRED,
                'correction', 'stock_correction' => StockMovement::TYPE_STOCK_CORRECTION,
                default => ($diff > 0 ? StockMovement::TYPE_ADJUSTMENT_IN : StockMovement::TYPE_ADJUSTMENT_OUT),
            };

            $product->update([
                'stock_quantity' => $newStockQuantity,
            ]);

            $fullReason = $reason . (!empty($notes) ? " (Notes: {$notes})" : '');

            StockMovement::create([
                'product_id' => $product->id,
                'type' => $canonicalType,
                'quantity' => $diff,
                'stock_before' => $stockBefore,
                'stock_after' => $newStockQuantity,
                'unit_cost' => (float) $product->wholesale_cost,
                'reference_type' => 'Adjustment',
                'reason' => $fullReason,
                'created_by' => $userName ?? auth()->user()?->name ?? 'Admin',
            ]);

            return $product;
        });
    }

    /**
     * Record damaged stock deduction.
     */
    public function recordDamage(int $productId, int $quantity, string $reason, ?string $userName = null): Product
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException("Damage quantity must be greater than zero.");
        }

        return DB::transaction(function () use ($productId, $quantity, $reason, $userName) {
            $product = Product::lockForUpdate()->findOrFail($productId);
            $newStock = max(0, (int) $product->stock_quantity - $quantity);

            return $this->adjustStock(
                $productId,
                $newStock,
                "Damaged stock: {$reason}",
                'Damage',
                $userName
            );
        });
    }

    /**
     * Record expired stock deduction.
     */
    public function recordExpiry(int $productId, int $quantity, string $reason, ?string $userName = null): Product
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException("Expired quantity must be greater than zero.");
        }

        return DB::transaction(function () use ($productId, $quantity, $reason, $userName) {
            $product = Product::lockForUpdate()->findOrFail($productId);
            $newStock = max(0, (int) $product->stock_quantity - $quantity);

            return $this->adjustStock(
                $productId,
                $newStock,
                "Expired stock: {$reason}",
                'Expiry',
                $userName
            );
        });
    }

    /**
     * Reconcile Opening Stock: ensure every product has at least one opening stock entry.
     */
    public function reconcileOpeningStock(): int
    {
        $products = Product::where('stock_quantity', '>', 0)->get();
        $seededCount = 0;

        foreach ($products as $product) {
            $hasMovements = StockMovement::where('product_id', $product->id)->exists();
            if (!$hasMovements) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'Opening Stock',
                    'quantity' => $product->stock_quantity,
                    'stock_before' => 0,
                    'stock_after' => $product->stock_quantity,
                    'unit_cost' => (float) $product->wholesale_cost,
                    'reference_type' => 'Opening',
                    'reason' => 'Initial Inventory Balance Reconciliation',
                    'created_by' => 'System',
                ]);
                $seededCount++;
            }
        }

        return $seededCount;
    }

    /**
     * Start a new Physical Stock Count session.
     */
    public function startStockCount(?int $categoryId = null, ?string $notes = null, ?string $userName = null): StockCount
    {
        return DB::transaction(function () use ($categoryId, $notes, $userName) {
            $nextId = (StockCount::max('id') ?? 0) + 1;
            $countNumber = 'SC-' . str_pad((string)$nextId, 6, '0', STR_PAD_LEFT);

            $query = Product::where('status', 'active');
            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }
            $products = $query->orderBy('name')->get();

            $count = StockCount::create([
                'count_number' => $countNumber,
                'status' => 'counting',
                'category_id' => $categoryId,
                'total_items' => $products->count(),
                'variance_count' => 0,
                'variance_value' => 0.00,
                'notes' => $notes,
                'created_by' => $userName ?? auth()->user()?->name ?? 'Admin',
            ]);

            foreach ($products as $p) {
                StockCountItem::create([
                    'stock_count_id' => $count->id,
                    'product_id' => $p->id,
                    'system_quantity' => $p->stock_quantity,
                    'physical_quantity' => $p->stock_quantity, // Default to system, staff adjusts differences
                    'variance' => 0,
                    'unit_cost' => (float) $p->wholesale_cost,
                    'variance_value' => 0.00,
                ]);
            }

            return $count->load('items.product');
        });
    }

    /**
     * Update a counted item and recalculate session variance.
     */
    public function updateStockCountItem(int $itemId, int $physicalQuantity, ?string $notes = null): StockCountItem
    {
        return DB::transaction(function () use ($itemId, $physicalQuantity, $notes) {
            $item = StockCountItem::findOrFail($itemId);
            $variance = $physicalQuantity - $item->system_quantity;
            $varianceValue = round($variance * (float) $item->unit_cost, 2);

            $item->update([
                'physical_quantity' => $physicalQuantity,
                'variance' => $variance,
                'variance_value' => $varianceValue,
                'notes' => $notes ?? $item->notes,
            ]);

            // Recalculate parent count aggregates
            $count = $item->stockCount;
            $items = $count->items;
            $totalVarianceCount = $items->where('variance', '!=', 0)->count();
            $totalVarianceValue = $items->sum('variance_value');

            $count->update([
                'variance_count' => $totalVarianceCount,
                'variance_value' => $totalVarianceValue,
            ]);

            return $item;
        });
    }

    /**
     * Approve Stock Count and apply variance corrections to the ledger.
     */
    public function approveStockCount(int $countId, ?string $approvedBy = null): StockCount
    {
        return DB::transaction(function () use ($countId, $approvedBy) {
            $count = StockCount::with('items.product')->lockForUpdate()->findOrFail($countId);

            if ($count->status === 'approved') {
                return $count;
            }

            $approver = $approvedBy ?? auth()->user()?->name ?? 'Admin';

            foreach ($count->items as $item) {
                if ($item->variance != 0 && $item->physical_quantity !== null) {
                    $product = Product::lockForUpdate()->findOrFail($item->product_id);
                    $stockBefore = (int) $product->stock_quantity;
                    $stockAfter = (int) $item->physical_quantity;

                    $product->update([
                        'stock_quantity' => $stockAfter,
                    ]);

                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'Physical Count Correction',
                        'quantity' => $item->variance,
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockAfter,
                        'unit_cost' => (float) $item->unit_cost,
                        'reference_type' => 'StockCount',
                        'reference_id' => $count->id,
                        'reason' => "Physical Inventory Count #{$count->count_number} correction",
                        'created_by' => $approver,
                    ]);
                }
            }

            $count->update([
                'status' => 'approved',
                'approved_by' => $approver,
                'approved_at' => now(),
            ]);

            return $count;
        });
    }

    /**
     * Get aggregated inventory KPIs for the dashboard cards.
     */
    public function getInventoryKPIs(): array
    {
        $today = Carbon::today()->toDateString();
        $sevenDaysOut = Carbon::today()->addDays(7)->toDateString();

        $stats = Product::where('status', 'active')
            ->selectRaw("
                COUNT(*) as total_products,
                COALESCE(SUM(stock_quantity), 0) as total_units,
                COALESCE(SUM(stock_quantity * wholesale_cost), 0) as cost_value,
                COALESCE(SUM(stock_quantity * retail_price), 0) as retail_value,
                COUNT(CASE WHEN (stock_quantity - reserved_quantity) <= minimum_stock_level AND (stock_quantity - reserved_quantity) > 0 THEN 1 END) as low_stock_count,
                COUNT(CASE WHEN (stock_quantity - reserved_quantity) <= 0 THEN 1 END) as out_of_stock_count,
                COUNT(CASE WHEN stock_quantity < 0 THEN 1 END) as negative_stock_count,
                COUNT(CASE WHEN expiry_date IS NOT NULL AND expiry_date <= ? AND stock_quantity > 0 THEN 1 END) as expiring_soon_count
            ", [$sevenDaysOut])->first();

        $costVal = (float) ($stats->cost_value ?? 0);
        $retailVal = (float) ($stats->retail_value ?? 0);

        return [
            'total_products' => (int) ($stats->total_products ?? 0),
            'total_units' => (int) ($stats->total_units ?? 0),
            'cost_value' => $costVal,
            'retail_value' => $retailVal,
            'potential_margin' => max(0, $retailVal - $costVal),
            'low_stock_count' => (int) ($stats->low_stock_count ?? 0),
            'out_of_stock_count' => (int) ($stats->out_of_stock_count ?? 0),
            'negative_stock_count' => (int) ($stats->negative_stock_count ?? 0),
            'expiring_soon_count' => (int) ($stats->expiring_soon_count ?? 0),
        ];
    }

    /**
     * Get Reorder Recommendations for low-stock products.
     */
    public function getReorderRecommendations(int $limit = 50)
    {
        return Product::with('category')
            ->where('status', 'active')
            ->whereRaw('(stock_quantity - reserved_quantity) <= minimum_stock_level')
            ->orderByRaw('(stock_quantity - reserved_quantity) ASC')
            ->take($limit)
            ->get()
            ->map(function ($p) {
                $available = max(0, $p->stock_quantity - $p->reserved_quantity);
                $target = $p->maximum_stock_level ?: ($p->minimum_stock_level * 3);
                $suggestedQty = max(10, $target - $available);

                return [
                    'product' => $p,
                    'current_stock' => $p->stock_quantity,
                    'reserved' => $p->reserved_quantity,
                    'available' => $available,
                    'reorder_level' => $p->minimum_stock_level,
                    'suggested_quantity' => $suggestedQty,
                    'estimated_cost' => round($suggestedQty * (float) $p->wholesale_cost, 2),
                    'supplier' => $p->supplier_name ?? 'Local Wholesaler',
                ];
            });
    }

    /**
     * Get Category-level inventory valuation breakdown.
     */
    public function getValuationByCategory()
    {
        return Category::with(['products' => function ($q) {
            $q->where('status', 'active');
        }])->get()->map(function ($cat) {
            $products = $cat->products;
            $cost = $products->sum(fn ($p) => $p->stock_quantity * (float) $p->wholesale_cost);
            $retail = $products->sum(fn ($p) => $p->stock_quantity * (float) $p->retail_price);
            $units = $products->sum('stock_quantity');

            return [
                'category_id' => $cat->id,
                'name' => $cat->name,
                'product_count' => $products->count(),
                'total_units' => $units,
                'cost_value' => round($cost, 2),
                'retail_value' => round($retail, 2),
                'potential_margin' => round(max(0, $retail - $cost), 2),
            ];
        });
    }

    /**
     * Create a new Stock Receipt / Goods Received Note (GRN).
     * Draft by default without affecting stock quantity.
     */
    public function createStockReceipt(array $header, array $items, string $userName = 'Admin'): StockReceipt
    {
        return DB::transaction(function () use ($header, $items, $userName) {
            $grnNumber = StockReceipt::generateNextGrnNumber();
            $supplier = null;
            $supplierName = $header['supplier_name_snapshot'] ?? null;

            if (!empty($header['supplier_id'])) {
                $supplier = Supplier::find($header['supplier_id']);
                if ($supplier) {
                    $supplierName = $supplier->name;
                }
            }

            // Calculate totals server-side
            $subtotal = 0.00;
            $totalTax = 0.00;
            $lineDiscount = 0.00;

            $receipt = StockReceipt::create([
                'grn_number' => $grnNumber,
                'supplier_id' => $supplier?->id,
                'supplier_name_snapshot' => $supplierName,
                'supplier_invoice_number' => $header['supplier_invoice_number'] ?? null,
                'invoice_date' => !empty($header['invoice_date']) ? Carbon::parse($header['invoice_date'])->format('Y-m-d') : null,
                'purchase_reference' => $header['purchase_reference'] ?? null,
                'receiving_date' => !empty($header['receiving_date']) ? Carbon::parse($header['receiving_date'])->format('Y-m-d') : Carbon::today()->format('Y-m-d'),
                'status' => $header['status'] ?? 'draft',
                'subtotal' => 0.00,
                'discount' => (float) ($header['discount'] ?? 0.00),
                'tax_amount' => 0.00,
                'other_charges' => (float) ($header['other_charges'] ?? 0.00),
                'total_amount' => 0.00,
                'payment_status' => $header['payment_status'] ?? 'unpaid',
                'notes' => $header['notes'] ?? null,
                'attachment_url' => $header['attachment_url'] ?? null,
                'created_by' => $userName,
            ]);

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $qtyExpected = (int) ($item['quantity_expected'] ?? $item['quantity_received'] ?? 1);
                $qtyReceived = (int) ($item['quantity_received'] ?? 1);
                $qtyDamaged = (int) ($item['quantity_damaged'] ?? 0);
                $qtySellable = max(0, $qtyReceived - $qtyDamaged);

                $unitCost = isset($item['unit_cost']) ? (float) $item['unit_cost'] : (float) $product->wholesale_cost;
                $disc = (float) ($item['discount'] ?? 0.00);
                $tax = (float) ($item['tax_amount'] ?? 0.00);
                $itemSubtotal = round(max(0, ($qtyReceived * $unitCost) - $disc + $tax), 2);

                $subtotal += ($qtyReceived * $unitCost);
                $lineDiscount += $disc;
                $totalTax += $tax;

                StockReceiptItem::create([
                    'stock_receipt_id' => $receipt->id,
                    'product_id' => $product->id,
                    'barcode' => $item['barcode'] ?? $product->barcode,
                    'product_name' => $product->name,
                    'quantity_expected' => $qtyExpected,
                    'quantity_received' => $qtyReceived,
                    'quantity_damaged' => $qtyDamaged,
                    'quantity_sellable' => $qtySellable,
                    'unit_cost' => $unitCost,
                    'discount' => $disc,
                    'tax_amount' => $tax,
                    'subtotal' => $itemSubtotal,
                    'batch_number' => $item['batch_number'] ?? null,
                    'expiry_date' => !empty($item['expiry_date']) ? Carbon::parse($item['expiry_date'])->format('Y-m-d') : null,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            $headerDiscount = (float) ($header['discount'] ?? 0.00);
            $otherCharges = (float) ($header['other_charges'] ?? 0.00);
            $grandTotal = round(max(0, $subtotal - $lineDiscount - $headerDiscount + $totalTax + $otherCharges), 2);

            $receipt->update([
                'subtotal' => round($subtotal, 2),
                'discount' => round($headerDiscount + $lineDiscount, 2),
                'tax_amount' => round($totalTax, 2),
                'total_amount' => $grandTotal,
            ]);

            return $receipt->load(['supplier', 'items.product']);
        });
    }

    /**
     * Update an unconfirmed Draft GRN.
     */
    public function updateStockReceipt(StockReceipt $receipt, array $header, array $items): StockReceipt
    {
        if ($receipt->status === 'received') {
            throw new \InvalidArgumentException("Completed receipts are immutable and cannot be edited.");
        }

        return DB::transaction(function () use ($receipt, $header, $items) {
            $supplierName = $header['supplier_name_snapshot'] ?? $receipt->supplier_name_snapshot;
            if (!empty($header['supplier_id'])) {
                $supplier = Supplier::find($header['supplier_id']);
                if ($supplier) {
                    $supplierName = $supplier->name;
                }
            }

            $receipt->update([
                'supplier_id' => $header['supplier_id'] ?? $receipt->supplier_id,
                'supplier_name_snapshot' => $supplierName,
                'supplier_invoice_number' => $header['supplier_invoice_number'] ?? $receipt->supplier_invoice_number,
                'invoice_date' => !empty($header['invoice_date']) ? Carbon::parse($header['invoice_date'])->format('Y-m-d') : $receipt->invoice_date,
                'purchase_reference' => $header['purchase_reference'] ?? $receipt->purchase_reference,
                'receiving_date' => !empty($header['receiving_date']) ? Carbon::parse($header['receiving_date'])->format('Y-m-d') : $receipt->receiving_date,
                'status' => $header['status'] ?? $receipt->status,
                'payment_status' => $header['payment_status'] ?? $receipt->payment_status,
                'notes' => $header['notes'] ?? $receipt->notes,
                'attachment_url' => $header['attachment_url'] ?? $receipt->attachment_url,
            ]);

            // Replace items
            $receipt->items()->delete();

            $subtotal = 0.00;
            $totalTax = 0.00;
            $lineDiscount = 0.00;

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $qtyExpected = (int) ($item['quantity_expected'] ?? $item['quantity_received'] ?? 1);
                $qtyReceived = (int) ($item['quantity_received'] ?? 1);
                $qtyDamaged = (int) ($item['quantity_damaged'] ?? 0);
                $qtySellable = max(0, $qtyReceived - $qtyDamaged);

                $unitCost = isset($item['unit_cost']) ? (float) $item['unit_cost'] : (float) $product->wholesale_cost;
                $disc = (float) ($item['discount'] ?? 0.00);
                $tax = (float) ($item['tax_amount'] ?? 0.00);
                $itemSubtotal = round(max(0, ($qtyReceived * $unitCost) - $disc + $tax), 2);

                $subtotal += ($qtyReceived * $unitCost);
                $lineDiscount += $disc;
                $totalTax += $tax;

                StockReceiptItem::create([
                    'stock_receipt_id' => $receipt->id,
                    'product_id' => $product->id,
                    'barcode' => $item['barcode'] ?? $product->barcode,
                    'product_name' => $product->name,
                    'quantity_expected' => $qtyExpected,
                    'quantity_received' => $qtyReceived,
                    'quantity_damaged' => $qtyDamaged,
                    'quantity_sellable' => $qtySellable,
                    'unit_cost' => $unitCost,
                    'discount' => $disc,
                    'tax_amount' => $tax,
                    'subtotal' => $itemSubtotal,
                    'batch_number' => $item['batch_number'] ?? null,
                    'expiry_date' => !empty($item['expiry_date']) ? Carbon::parse($item['expiry_date'])->format('Y-m-d') : null,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            $headerDiscount = (float) ($header['discount'] ?? 0.00);
            $otherCharges = (float) ($header['other_charges'] ?? 0.00);
            $grandTotal = round(max(0, $subtotal - $lineDiscount - $headerDiscount + $totalTax + $otherCharges), 2);

            $receipt->update([
                'subtotal' => round($subtotal, 2),
                'discount' => round($headerDiscount + $lineDiscount, 2),
                'tax_amount' => round($totalTax, 2),
                'other_charges' => round($otherCharges, 2),
                'total_amount' => $grandTotal,
            ]);

            return $receipt->fresh(['supplier', 'items.product']);
        });
    }

    /**
     * Atomically Confirm a Stock Receipt (GRN).
     * Commits quantities to physical stock, updates weighted average wholesale cost,
     * updates expiry & supplier references, and generates immutable stock ledger movements.
     */
    public function confirmStockReceipt(int $receiptId, string $userName = 'Admin'): StockReceipt
    {
        return DB::transaction(function () use ($receiptId, $userName) {
            $receipt = StockReceipt::lockForUpdate()->with('items')->findOrFail($receiptId);

            // Idempotency: if already completed, return existing confirmed receipt without duplicating stock additions
            if ($receipt->status === 'received') {
                return $receipt->load(['supplier', 'items.product']);
            }

            if ($receipt->status === 'cancelled') {
                throw new \InvalidArgumentException("Cannot confirm cancelled receipt {$receipt->grn_number}.");
            }

            $supplierName = $receipt->supplier_name_snapshot ?? ($receipt->supplier?->name ?? 'Supplier');

            foreach ($receipt->items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item->product_id);
                $sellableQty = (int) ($item->quantity_sellable > 0 ? $item->quantity_sellable : max(0, (int)$item->quantity_received - (int)$item->quantity_damaged));

                if ($sellableQty <= 0) {
                    continue;
                }

                $stockBefore = (int) $product->stock_quantity;
                $stockAfter = $stockBefore + $sellableQty;

                // Weighted Average Cost calculation
                $unitCost = (float) $item->unit_cost;
                if ($unitCost > 0 && $stockAfter > 0) {
                    $currentTotal = $stockBefore * (float) $product->wholesale_cost;
                    $incomingTotal = $sellableQty * $unitCost;
                    $newAvgCost = round(($currentTotal + $incomingTotal) / $stockAfter, 2);
                    $product->wholesale_cost = $newAvgCost;
                }

                if (!empty($supplierName)) {
                    $product->supplier_name = $supplierName;
                }

                if (!empty($item->expiry_date)) {
                    $product->expiry_date = $item->expiry_date;
                }

                $product->stock_quantity = $stockAfter;
                $product->save();

                // Append-only stock ledger movement
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => StockMovement::TYPE_PURCHASE_RECEIVED,
                    'quantity' => $sellableQty,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'unit_cost' => $unitCost,
                    'reference_type' => 'GRN',
                    'reference_id' => $receipt->id,
                    'reason' => "Stock Received via {$receipt->grn_number}" . ($receipt->supplier_invoice_number ? " (Inv #{$receipt->supplier_invoice_number})" : ''),
                    'created_by' => $userName,
                ]);
            }

            $receipt->update([
                'status' => 'received',
                'confirmed_by' => $userName,
                'confirmed_at' => Carbon::now(),
            ]);

            return $receipt->fresh(['supplier', 'items.product']);
        });
    }

    /**
     * Cancel an unconfirmed Draft GRN.
     */
    public function cancelStockReceipt(int $receiptId, string $userName = 'Admin'): StockReceipt
    {
        $receipt = StockReceipt::findOrFail($receiptId);
        if ($receipt->status === 'received') {
            throw new \InvalidArgumentException("Completed receipts cannot be cancelled. Use Supplier Return to reverse received stock.");
        }

        $receipt->update([
            'status' => 'cancelled',
            'notes' => ($receipt->notes ? $receipt->notes . "\n" : "") . "Cancelled by {$userName} on " . Carbon::now()->toDateTimeString(),
        ]);

        return $receipt;
    }

    /**
     * Supplier Return from completed GRN.
     */
    public function returnStockFromReceipt(int $receiptId, int $productId, int $quantity, string $reason, string $userName = 'Admin'): Product
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException("Return quantity must be greater than zero.");
        }

        return DB::transaction(function () use ($receiptId, $productId, $quantity, $reason, $userName) {
            $receipt = StockReceipt::findOrFail($receiptId);
            $product = Product::lockForUpdate()->findOrFail($productId);

            $stockBefore = (int) $product->stock_quantity;
            $stockAfter = max(0, $stockBefore - $quantity);

            $product->stock_quantity = $stockAfter;
            $product->save();

            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'Return',
                'quantity' => -$quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'unit_cost' => (float) $product->wholesale_cost,
                'reference_type' => 'GRN',
                'reference_id' => $receipt->id,
                'reason' => "Supplier Return: {$reason} (Ref: {$receipt->grn_number})",
                'created_by' => $userName,
            ]);

            return $product;
        });
    }

    /**
     * Get KPI Summary for Stock Receiving Dashboard.
     */
    public function getReceivingKPIs(): array
    {
        $today = Carbon::today()->format('Y-m-d');
        $startOfMonth = Carbon::now()->startOfMonth()->format('Y-m-d');

        $draftCount = StockReceipt::where('status', 'draft')->count();
        $pendingCount = StockReceipt::where('status', 'pending_review')->count();

        $receivedTodayQuery = StockReceipt::where('status', 'received')
            ->whereDate('receiving_date', $today);

        $receivedTodayCount = $receivedTodayQuery->count();
        $receivedTodayValue = (float) $receivedTodayQuery->sum('total_amount');

        $monthQuery = StockReceipt::where('status', 'received')
            ->whereDate('receiving_date', '>=', $startOfMonth);

        $monthCount = $monthQuery->count();
        $monthTotalValue = (float) $monthQuery->sum('total_amount');

        return [
            'draft_count' => $draftCount,
            'pending_count' => $pendingCount,
            'received_today_count' => $receivedTodayCount,
            'received_today_value' => round($receivedTodayValue, 2),
            'month_receipts_count' => $monthCount,
            'month_total_value' => round($monthTotalValue, 2),
        ];
    }

    /**
     * Diagnostic Inventory Reconciliation Tool (PRD Section 62).
     * Compares recorded movements in stock ledger against current physical & reserved stock.
     */
    public function getReconciliationReport(?int $categoryId = null, ?string $search = null): array
    {
        $query = Product::with('category')->where('status', 'active');

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($search && trim($search) !== '') {
            $term = trim($search);
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('barcode', 'like', "%{$term}%")
                  ->orWhere('sku', 'like', "%{$term}%");
            });
        }

        $products = $query->orderBy('name')->get();
        $discrepancies = [];
        $totalChecked = $products->count();

        // Query movement sums in batch for efficiency
        $productIds = $products->pluck('id')->toArray();
        $movementSums = StockMovement::whereIn('product_id', $productIds)
            ->selectRaw('product_id, COALESCE(SUM(quantity), 0) as total_diff')
            ->groupBy('product_id')
            ->pluck('total_diff', 'product_id');

        $lastMovements = StockMovement::whereIn('product_id', $productIds)
            ->selectRaw('product_id, MAX(created_at) as last_movement')
            ->groupBy('product_id')
            ->pluck('last_movement', 'product_id');

        foreach ($products as $p) {
            $currentStock = (int) $p->stock_quantity;
            $reservedStock = (int) $p->reserved_quantity;
            $availableStock = max(0, $currentStock - $reservedStock);
            $recordedNetDiff = (int) ($movementSums[$p->id] ?? 0);

            // Check if there is an opening stock entry or if net movements equals current stock
            $hasLedger = isset($movementSums[$p->id]);
            $isDiscrepant = false;
            $discrepancyDiff = 0;

            if ($hasLedger) {
                // If ledger exists, net diff should equal stock_quantity unless unrecorded opening stock
                if ($recordedNetDiff !== $currentStock) {
                    $isDiscrepant = true;
                    $discrepancyDiff = $currentStock - $recordedNetDiff;
                }
            } else if ($currentStock > 0) {
                // No ledger entries at all, but product has positive stock
                $isDiscrepant = true;
                $discrepancyDiff = $currentStock;
            }

            // Invariant check: reserved > physical
            $invariantViolation = ($reservedStock > $currentStock) || ($currentStock < 0);

            if ($isDiscrepant || $invariantViolation) {
                $discrepancies[] = [
                    'product_id' => $p->id,
                    'name' => $p->name,
                    'product_name' => $p->name,
                    'sku' => $p->sku,
                    'barcode' => $p->barcode,
                    'category' => $p->category?->name ?? 'General',
                    'physical_stock' => $currentStock,
                    'current_stock' => $currentStock,
                    'reserved_stock' => $reservedStock,
                    'available_stock' => $availableStock,
                    'recorded_net_movements' => $recordedNetDiff,
                    'computed_stock' => $recordedNetDiff,
                    'discrepancy' => $discrepancyDiff,
                    'difference' => $discrepancyDiff,
                    'has_invariant_violation' => $invariantViolation,
                    'last_movement' => $lastMovements[$p->id] ?? null,
                ];
            }
        }

        return [
            'summary' => [
                'total_products' => $totalChecked,
                'discrepancies_count' => count($discrepancies),
                'total_movements_checked' => StockMovement::count(),
            ],
            'total_checked' => $totalChecked,
            'discrepancies_count' => count($discrepancies),
            'discrepancies' => $discrepancies,
        ];
    }

    /**
     * Apply Auditable Reconciliation Correction (PRD Section 62).
     */
    public function applyReconciliationCorrection(int $productId, int $newPhysicalStock, string $reason, string $userName = 'Admin'): Product
    {
        if (empty(trim($reason))) {
            throw new \InvalidArgumentException("A mandatory audit reason is required to correct an inventory discrepancy.");
        }

        return $this->adjustStock(
            $productId,
            $newPhysicalStock,
            "Reconciliation Correction: {$reason}",
            StockMovement::TYPE_STOCK_CORRECTION,
            $userName
        );
    }
}
