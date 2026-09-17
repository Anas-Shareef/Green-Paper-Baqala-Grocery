<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Api\v1\BaseApiController;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminInventoryController extends BaseApiController
{
    /**
     * Get Inventory Summary and Stock Movements
     */
    public function index(Request $request): JsonResponse
    {
        $products = Product::with('category')
            ->orderBy('stock_quantity', 'asc')
            ->get();

        $recentMovements = StockMovement::with('product')
            ->orderBy('id', 'desc')
            ->take(30)
            ->get();

        return $this->successResponse([
            'products' => $products,
            'recent_movements' => $recentMovements,
            'low_stock_count' => Product::whereColumn('stock_quantity', '<=', 'minimum_stock_level')->count(),
            'out_of_stock_count' => Product::where('stock_quantity', '<=', 0)->count(),
        ], 'Inventory data retrieved successfully');
    }

    /**
     * Adjust Product Stock Level (Record Stock Movement in DB Transaction)
     */
    public function adjustStock(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'type' => 'required|in:purchase,sale,adjustment,damage,return',
            'quantity' => 'required|integer',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        try {
            $movement = DB::transaction(function () use ($request) {
                $product = Product::lockForUpdate()->findOrFail($request->input('product_id'));
                $prevQty = $product->stock_quantity;
                $changeQty = (int) $request->input('quantity');

                $newQty = max(0, $prevQty + $changeQty);
                $product->update(['stock_quantity' => $newQty]);

                return StockMovement::create([
                    'product_id' => $product->id,
                    'type' => $request->input('type'),
                    'quantity' => $changeQty,
                    'previous_quantity' => $prevQty,
                    'new_quantity' => $newQty,
                    'reason' => $request->input('reason', 'Manual Stock Adjustment'),
                ]);
            });

            return $this->successResponse($movement->load('product'), 'Stock adjusted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), [], 400);
        }
    }

    /**
     * Get Stock Movements History with Pagination
     */
    public function movements(Request $request): JsonResponse
    {
        $query = StockMovement::with('product');

        if ($request->has('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        $movements = $query->orderBy('id', 'desc')->paginate(30);

        return $this->paginatedResponse($movements, 'Stock movements retrieved successfully');
    }
}
