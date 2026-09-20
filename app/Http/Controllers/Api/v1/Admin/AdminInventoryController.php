<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Api\v1\BaseApiController;
use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class AdminInventoryController extends BaseApiController
{
    /**
     * Get Paginated Inventory List with Filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('category:id,name,slug')->where('status', 'active');

        if ($request->has('q') && !empty(trim($request->input('q')))) {
            $term = trim($request->input('q'));
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('brand', 'like', "%{$term}%");
            });
        }

        if ($request->has('category_id') && !empty($request->input('category_id'))) {
            $query->where('category_id', $request->input('category_id'));
        }

        $stockFilter = $request->input('status');
        if ($stockFilter === 'low_stock') {
            $query->whereRaw('(stock_quantity - reserved_quantity) <= minimum_stock_level AND (stock_quantity - reserved_quantity) > 0');
        } elseif ($stockFilter === 'out_of_stock') {
            $query->whereRaw('(stock_quantity - reserved_quantity) <= 0');
        } elseif ($stockFilter === 'in_stock') {
            $query->whereRaw('(stock_quantity - reserved_quantity) > minimum_stock_level');
        } elseif ($stockFilter === 'negative') {
            $query->where('stock_quantity', '<', 0);
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $products = $query->orderByRaw('(stock_quantity - reserved_quantity) ASC')->paginate($perPage);

        $mapped = $products->through(function ($p) {
            $available = max(0, $p->stock_quantity - $p->reserved_quantity);
            $status = $p->stock_quantity < 0 ? 'negative' : ($available <= 0 ? 'out_of_stock' : ($available <= $p->minimum_stock_level ? 'low_stock' : 'in_stock'));

            return [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'brand' => $p->brand,
                'unit' => $p->unit,
                'category' => $p->category?->name,
                'wholesale_cost' => (float) $p->wholesale_cost,
                'retail_price' => (float) $p->retail_price,
                'stock_quantity' => $p->stock_quantity,
                'reserved_quantity' => $p->reserved_quantity,
                'available_quantity' => $available,
                'minimum_stock_level' => $p->minimum_stock_level,
                'maximum_stock_level' => $p->maximum_stock_level,
                'expiry_date' => $p->expiry_date ? $p->expiry_date->toDateString() : null,
                'supplier_name' => $p->supplier_name,
                'status' => $status,
            ];
        });

        return $this->paginatedResponse($mapped, 'Inventory products retrieved successfully');
    }

    /**
     * Get Inventory Summary KPIs & Valuation
     */
    public function summary(InventoryService $inventoryService): JsonResponse
    {
        $kpis = $inventoryService->getInventoryKPIs();
        return $this->successResponse($kpis, 'Inventory summary KPIs retrieved successfully');
    }

    /**
     * Show Single Product Inventory Breakdown & Movements
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::with(['category', 'stockMovements' => function ($q) {
            $q->orderBy('id', 'desc')->limit(20);
        }])->findOrFail($id);

        $available = max(0, $product->stock_quantity - $product->reserved_quantity);

        return $this->successResponse([
            'product' => $product,
            'available_quantity' => $available,
            'movements' => $product->stockMovements,
        ], 'Product inventory details retrieved successfully');
    }

    /**
     * Adjust Product Stock Level
     */
    public function adjustStock(Request $request, InventoryService $inventoryService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:0', // target new stock quantity
            'type' => 'required|string|in:Damage,Expiry,Correction,Lost,Found,Manual Adjustment',
            'reason' => 'required|string|min:3',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        try {
            $product = $inventoryService->adjustStock(
                (int) $request->input('product_id'),
                (int) $request->input('quantity'),
                $request->input('reason'),
                $request->input('type'),
                $request->user()?->name ?? 'Admin API',
                $request->input('notes')
            );

            return $this->successResponse($product, 'Stock adjusted and ledger movement recorded successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), [], 400);
        }
    }

    /**
     * Get Stock Movements History with Pagination
     */
    public function movements(Request $request): JsonResponse
    {
        $query = StockMovement::with('product:id,name,barcode');

        if ($request->has('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        $perPage = min((int) $request->input('per_page', 30), 100);
        $movements = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->paginatedResponse($movements, 'Stock movements retrieved successfully');
    }

    /**
     * Get Reorder Recommendations
     */
    public function reorder(InventoryService $inventoryService): JsonResponse
    {
        $recommendations = $inventoryService->getReorderRecommendations(50);
        return $this->successResponse($recommendations, 'Reorder recommendations retrieved successfully');
    }

    /**
     * Get Valuation by Category
     */
    public function valuation(InventoryService $inventoryService): JsonResponse
    {
        $valuation = $inventoryService->getValuationByCategory();
        return $this->successResponse($valuation, 'Category inventory valuation retrieved successfully');
    }

    /**
     * List Stock Count Sessions
     */
    public function listCounts(): JsonResponse
    {
        $counts = StockCount::with('category:id,name')
            ->orderBy('id', 'desc')
            ->paginate(20);

        return $this->paginatedResponse($counts, 'Stock counts retrieved successfully');
    }

    /**
     * Start New Stock Count Session
     */
    public function startCount(Request $request, InventoryService $inventoryService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'nullable|exists:categories,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        try {
            $count = $inventoryService->startStockCount(
                $request->input('category_id'),
                $request->input('notes'),
                $request->user()?->name ?? 'Admin API'
            );

            return $this->successResponse($count, 'Stock count session initialized successfully', 201);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), [], 400);
        }
    }

    /**
     * Get Product-Level Stock Ledger with Server-Side Pagination (PRD Section 36)
     */
    public function ledger(Request $request, int $id): JsonResponse
    {
        $product = Product::with('category')->findOrFail($id);
        $perPage = min((int) $request->input('per_page', 25), 100);

        $movements = StockMovement::where('product_id', $id)
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return $this->paginatedResponse($movements, "Stock ledger for {$product->name} retrieved successfully");
    }

    /**
     * Diagnostic Inventory Reconciliation Tool (PRD Section 62)
     */
    public function reconciliation(Request $request, InventoryService $inventoryService): JsonResponse
    {
        $categoryId = $request->input('category_id') ? (int) $request->input('category_id') : null;
        $search = $request->input('q');

        $report = $inventoryService->getReconciliationReport($categoryId, $search);
        return $this->successResponse($report, 'Inventory reconciliation report retrieved successfully');
    }

    /**
     * Apply Auditable Reconciliation Correction
     */
    public function correctReconciliation(Request $request, int $id, InventoryService $inventoryService): JsonResponse
    {
        $request->validate([
            'new_stock_quantity' => 'required|integer|min:0',
            'reason' => 'required|string|min:3|max:255',
        ]);

        try {
            $product = $inventoryService->applyReconciliationCorrection(
                $id,
                (int) $request->input('new_stock_quantity'),
                $request->input('reason'),
                $request->user()?->name ?? 'Admin API'
            );

            return $this->successResponse($product, 'Inventory discrepancy corrected and ledger entry recorded');
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), [], 400);
        }
    }
}
