<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Record stock receiving/purchase.
     */
    public function receiveStock(int $productId, int $quantity, ?string $reason = 'Stock Purchase Receiving', ?string $userName = null): Product
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException("Receiving quantity must be greater than zero.");
        }

        return DB::transaction(function () use ($productId, $quantity, $reason, $userName) {
            $product = Product::lockForUpdate()->findOrFail($productId);
            $stockBefore = $product->stock_quantity;
            $stockAfter = $stockBefore + $quantity;

            $product->update([
                'stock_quantity' => $stockAfter,
            ]);

            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'Purchase',
                'quantity' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reference_type' => 'Receiving',
                'reason' => $reason,
                'created_by' => $userName ?? auth()->user()?->name ?? 'Admin',
            ]);

            return $product;
        });
    }

    /**
     * Adjust product stock manually with mandatory reason.
     */
    public function adjustStock(int $productId, int $newStockQuantity, string $reason, string $type = 'Manual Adjustment', ?string $userName = null): Product
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException("A mandatory reason must be provided for manual stock adjustment.");
        }

        return DB::transaction(function () use ($productId, $newStockQuantity, $reason, $type, $userName) {
            $product = Product::lockForUpdate()->findOrFail($productId);
            $stockBefore = $product->stock_quantity;
            $diff = $newStockQuantity - $stockBefore;

            if ($diff === 0) {
                return $product;
            }

            $product->update([
                'stock_quantity' => $newStockQuantity,
            ]);

            StockMovement::create([
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => $diff,
                'stock_before' => $stockBefore,
                'stock_after' => $newStockQuantity,
                'reference_type' => 'Adjustment',
                'reason' => $reason,
                'created_by' => $userName ?? auth()->user()?->name ?? 'Admin',
            ]);

            return $product;
        });
    }
}
