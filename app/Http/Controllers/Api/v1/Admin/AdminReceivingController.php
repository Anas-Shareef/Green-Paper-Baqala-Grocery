<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Api\v1\BaseApiController;
use App\Models\Product;
use App\Models\StockReceipt;
use App\Models\Supplier;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminReceivingController extends BaseApiController
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * List Stock Receipts / GRNs with search and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockReceipt::with(['supplier', 'items.product']);

        if ($request->has('status') && !empty($request->input('status'))) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('supplier_id') && !empty($request->input('supplier_id'))) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        if ($request->has('date_from') && !empty($request->input('date_from'))) {
            $query->whereDate('receiving_date', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to') && !empty($request->input('date_to'))) {
            $query->whereDate('receiving_date', '<=', $request->input('date_to'));
        }

        if ($request->has('q') && !empty(trim($request->input('q')))) {
            $q = trim($request->input('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('grn_number', 'like', "%{$q}%")
                    ->orWhere('supplier_invoice_number', 'like', "%{$q}%")
                    ->orWhere('purchase_reference', 'like', "%{$q}%")
                    ->orWhere('supplier_name_snapshot', 'like', "%{$q}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $receipts = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->paginatedResponse($receipts, 'Stock receipts retrieved successfully');
    }

    /**
     * Get Dashboard KPIs for Receiving Station.
     */
    public function kpis(): JsonResponse
    {
        $kpis = $this->inventoryService->getReceivingKPIs();
        return $this->successResponse($kpis, 'Receiving KPIs retrieved successfully');
    }

    /**
     * Get single GRN details.
     */
    public function show(int $id): JsonResponse
    {
        $receipt = StockReceipt::with(['supplier', 'items.product'])->findOrFail($id);
        return $this->successResponse($receipt, 'Receipt details retrieved successfully');
    }

    /**
     * Create a new draft or submitted GRN.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'nullable|exists:suppliers,id',
            'supplier_name_snapshot' => 'nullable|string|max:255',
            'supplier_invoice_number' => 'nullable|string|max:255',
            'invoice_date' => 'nullable|date',
            'purchase_reference' => 'nullable|string|max:255',
            'receiving_date' => 'required|date',
            'status' => 'nullable|in:draft,pending_review',
            'discount' => 'nullable|numeric|min:0',
            'other_charges' => 'nullable|numeric|min:0',
            'payment_status' => 'nullable|in:unpaid,partially_paid,paid',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_received' => 'required|integer|min:1',
            'items.*.quantity_damaged' => 'nullable|integer|min:0',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
            'items.*.expiry_date' => 'nullable|date',
            'items.*.batch_number' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        // Check for duplicate supplier invoice
        if ($request->filled('supplier_id') && $request->filled('supplier_invoice_number')) {
            $existing = StockReceipt::where('supplier_id', $request->input('supplier_id'))
                ->where('supplier_invoice_number', trim($request->input('supplier_invoice_number')))
                ->where('status', '!=', 'cancelled')
                ->first();
            if ($existing) {
                return $this->errorResponse("Receipt {$existing->grn_number} already exists for this supplier invoice number.", 422);
            }
        }

        try {
            $userName = auth()->user()?->name ?? 'Admin';
            $receipt = $this->inventoryService->createStockReceipt(
                $request->only([
                    'supplier_id', 'supplier_name_snapshot', 'supplier_invoice_number',
                    'invoice_date', 'purchase_reference', 'receiving_date', 'status',
                    'discount', 'other_charges', 'payment_status', 'notes', 'attachment_url'
                ]),
                $request->input('items'),
                $userName
            );

            return $this->successResponse($receipt, 'Stock receipt created successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Update an existing draft GRN.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $receipt = StockReceipt::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'supplier_id' => 'nullable|exists:suppliers,id',
            'supplier_name_snapshot' => 'nullable|string|max:255',
            'supplier_invoice_number' => 'nullable|string|max:255',
            'invoice_date' => 'nullable|date',
            'purchase_reference' => 'nullable|string|max:255',
            'receiving_date' => 'nullable|date',
            'status' => 'nullable|in:draft,pending_review',
            'discount' => 'nullable|numeric|min:0',
            'other_charges' => 'nullable|numeric|min:0',
            'payment_status' => 'nullable|in:unpaid,partially_paid,paid',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_received' => 'required|integer|min:1',
            'items.*.quantity_damaged' => 'nullable|integer|min:0',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
            'items.*.expiry_date' => 'nullable|date',
            'items.*.batch_number' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        try {
            $updated = $this->inventoryService->updateStockReceipt(
                $receipt,
                $request->only([
                    'supplier_id', 'supplier_name_snapshot', 'supplier_invoice_number',
                    'invoice_date', 'purchase_reference', 'receiving_date', 'status',
                    'discount', 'other_charges', 'payment_status', 'notes', 'attachment_url'
                ]),
                $request->input('items')
            );

            return $this->successResponse($updated, 'Stock receipt updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Atomically Confirm Stock Receipt and Commit to Inventory Ledger.
     */
    public function confirm(Request $request, int $id): JsonResponse
    {
        try {
            $userName = auth()->user()?->name ?? 'Admin';
            $receipt = $this->inventoryService->confirmStockReceipt($id, $userName);

            return $this->successResponse($receipt, "Stock receipt {$receipt->grn_number} confirmed and inventory updated successfully");
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->errorResponse('Confirmation error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cancel draft receipt.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        try {
            $userName = auth()->user()?->name ?? 'Admin';
            $receipt = $this->inventoryService->cancelStockReceipt($id, $userName);

            return $this->successResponse($receipt, "Stock receipt {$receipt->grn_number} cancelled successfully");
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Supplier Return against completed receipt.
     */
    public function returnStock(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string|min:3|max:255',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        try {
            $userName = auth()->user()?->name ?? 'Admin';
            $product = $this->inventoryService->returnStockFromReceipt(
                $id,
                (int) $request->input('product_id'),
                (int) $request->input('quantity'),
                $request->input('reason'),
                $userName
            );

            return $this->successResponse([
                'product' => $product,
            ], "Stock return recorded successfully. Current stock: {$product->stock_quantity}");
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * List and Search Suppliers.
     */
    public function suppliers(Request $request): JsonResponse
    {
        $query = Supplier::query();

        if ($request->has('q') && !empty(trim($request->input('q')))) {
            $q = trim($request->input('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('contact_person', 'like', "%{$q}%");
            });
        }

        if ($request->has('status') && !empty($request->input('status'))) {
            $query->where('status', $request->input('status'));
        }

        $suppliers = $query->orderBy('name')->get()->map(function ($s) {
            return [
                'id' => $s->id,
                'name' => $s->name,
                'phone' => $s->phone,
                'email' => $s->email,
                'address' => $s->address,
                'contact_person' => $s->contact_person,
                'tax_number' => $s->tax_number,
                'status' => $s->status,
                'purchase_count' => $s->purchaseCount(),
                'last_purchase_date' => $s->lastPurchaseDate(),
            ];
        });

        return $this->successResponse($suppliers, 'Suppliers retrieved successfully');
    }

    /**
     * Create a new Supplier.
     */
    public function storeSupplier(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:suppliers,name',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'contact_person' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $supplier = Supplier::create($request->only([
            'name', 'phone', 'email', 'address', 'contact_person', 'tax_number', 'notes'
        ]));

        return $this->successResponse($supplier, 'Supplier created successfully', 201);
    }

    /**
     * Fast Barcode Lookup for Receiving Station (Datalogic QuickScan Lite).
     */
    public function barcodeLookup(string $barcode): JsonResponse
    {
        $barcode = trim($barcode);

        $product = Product::select([
            'id', 'category_id', 'barcode', 'sku', 'name', 'unit',
            'wholesale_cost', 'retail_price', 'stock_quantity', 'expiry_date', 'supplier_name'
        ])
        ->where('barcode', $barcode)
        ->first();

        if ($product) {
            return $this->successResponse([
                'found' => true,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku ?? ('SKU-' . $product->id),
                    'barcode' => $product->barcode,
                    'unit' => $product->unit ?? 'piece',
                    'wholesale_cost' => (float) $product->wholesale_cost,
                    'retail_price' => (float) $product->retail_price,
                    'stock_quantity' => (int) $product->stock_quantity,
                    'expiry_date' => $product->expiry_date ? $product->expiry_date->format('Y-m-d') : null,
                    'supplier_name' => $product->supplier_name,
                ],
            ], 'Product found');
        }

        return $this->successResponse([
            'found' => false,
            'barcode' => $barcode,
        ], 'Barcode not found');
    }

    /**
     * Quick Create Product for Unknown Barcode during Receiving Session.
     */
    public function quickCreateProduct(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'barcode' => 'required|string|max:100|unique:products,barcode',
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'unit' => 'nullable|string|max:50',
            'wholesale_cost' => 'required|numeric|min:0',
            'retail_price' => 'required|numeric|min:0',
            'supplier_name' => 'nullable|string|max:255',
            'expiry_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $categoryId = $request->input('category_id') ?? \App\Models\Category::first()?->id ?? 1;
        $sku = 'SKU-' . strtoupper(Str::random(6));

        $product = Product::create([
            'barcode' => trim($request->input('barcode')),
            'name' => trim($request->input('name')),
            'category_id' => $categoryId,
            'sku' => $sku,
            'unit' => $request->input('unit', 'piece'),
            'wholesale_cost' => $request->input('wholesale_cost'),
            'retail_price' => $request->input('retail_price'),
            'stock_quantity' => 0, // Stock intake is committed upon GRN confirmation
            'minimum_stock_level' => 5,
            'supplier_name' => $request->input('supplier_name'),
            'expiry_date' => $request->input('expiry_date'),
            'status' => 'active',
        ]);

        return $this->successResponse([
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'unit' => $product->unit,
            'wholesale_cost' => (float) $product->wholesale_cost,
            'retail_price' => (float) $product->retail_price,
            'stock_quantity' => (int) $product->stock_quantity,
            'expiry_date' => $product->expiry_date ? $product->expiry_date->format('Y-m-d') : null,
            'supplier_name' => $product->supplier_name,
        ], 'Product created successfully and ready for receiving', 201);
    }
}
