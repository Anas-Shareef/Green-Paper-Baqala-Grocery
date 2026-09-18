<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\StockMovement;
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

        return DB::transaction(function () use ($productId, $newStockQuantity, $reason, $type, $userName, $notes) {
            $product = Product::lockForUpdate()->findOrFail($productId);
            $stockBefore = (int) $product->stock_quantity;
            $diff = $newStockQuantity - $stockBefore;

            if ($diff === 0) {
                return $product;
            }

            $product->update([
                'stock_quantity' => $newStockQuantity,
            ]);

            $fullReason = $reason . (!empty($notes) ? " (Notes: {$notes})" : '');

            StockMovement::create([
                'product_id' => $product->id,
                'type' => $type,
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
}
