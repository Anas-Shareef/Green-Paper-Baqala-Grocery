<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Api\v1\BaseApiController;
use App\Models\Category;
use App\Models\Product;
use App\Services\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminProductController extends BaseApiController
{
    protected SupabaseStorageService $storageService;

    public function __construct(SupabaseStorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * List Products for Admin Management with Filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::select([
            'id', 'category_id', 'barcode', 'sku', 'name', 'brand', 'unit',
            'wholesale_cost', 'retail_price', 'stock_quantity', 'reserved_quantity',
            'minimum_stock_level', 'maximum_stock_level', 'image', 'status',
            'expiry_date', 'supplier_name'
        ])->with(['category' => function ($q) {
            $q->select('id', 'name');
        }]);

        if ($request->has('category_id') && !empty($request->input('category_id'))) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->has('status') && !empty($request->input('status'))) {
            $query->where('status', $request->input('status'));
        }

        // Stock Status filter: in_stock, low_stock, out_of_stock
        if ($request->has('stock_status') && !empty($request->input('stock_status'))) {
            $stockStatus = $request->input('stock_status');
            if ($stockStatus === 'out_of_stock') {
                $query->where('stock_quantity', '<=', 0);
            } elseif ($stockStatus === 'low_stock') {
                $query->where('stock_quantity', '>', 0)
                      ->whereColumn('stock_quantity', '<=', 'minimum_stock_level');
            } elseif ($stockStatus === 'in_stock') {
                $query->whereColumn('stock_quantity', '>', 'minimum_stock_level');
            }
        }

        if ($request->has('q') && !empty(trim($request->input('q')))) {
            $q = trim($request->input('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $products = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->paginatedResponse($products, 'Admin products retrieved successfully');
    }

    /**
     * Store New Product (Upload Image to Supabase Storage)
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'retail_price' => 'nullable|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
            'wholesale_cost' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string|max:50',
            'barcode' => 'nullable|string|max:100|unique:products,barcode',
            'sku' => 'nullable|string|max:100|unique:products,sku',
            'stock_quantity' => 'nullable|integer|min:0',
            'minimum_stock_level' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'image_file' => 'nullable|file|image|max:5120',
            'image_url' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $retailPrice = $request->input('retail_price', $request->input('price', 0));
        $wholesaleCost = $request->input('wholesale_cost', $request->input('cost_price', 0));
        $sku = $request->input('sku') ?: 'SKU-' . strtoupper(Str::random(6));
        $barcode = $request->input('barcode') ?: ('629' . rand(1000000000, 9999999999));

        $imageUrl = $request->input('image_url');
        if ($request->hasFile('image_file')) {
            $imageUrl = $this->storageService->uploadFile($request->file('image_file'), 'product-images', 'products');
        }

        $product = Product::create([
            'name' => trim($request->input('name')),
            'category_id' => $request->input('category_id'),
            'retail_price' => $retailPrice,
            'wholesale_cost' => $wholesaleCost,
            'unit' => $request->input('unit', 'piece'),
            'barcode' => $barcode,
            'sku' => $sku,
            'stock_quantity' => $request->input('stock_quantity', 0),
            'minimum_stock_level' => $request->input('minimum_stock_level', 5),
            'description' => $request->input('description'),
            'image' => $imageUrl,
            'status' => $request->input('status', 'active'),
        ]);

        return $this->successResponse($product->load('category'), 'Product created successfully', 201);
    }

    /**
     * Show Single Product Details
     */
    public function show(string $id): JsonResponse
    {
        $product = Product::with('category')->find($id);
        if (!$product) {
            return $this->errorResponse('Product not found', 404);
        }

        return $this->successResponse($product, 'Product details retrieved successfully');
    }

    /**
     * Update Existing Product (Supports Image Replace / Removal)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::find($id);
        if (!$product) {
            return $this->errorResponse('Product not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'category_id' => 'sometimes|required|exists:categories,id',
            'retail_price' => 'nullable|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
            'wholesale_cost' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string|max:50',
            'barcode' => 'nullable|string|max:100|unique:products,barcode,' . $id,
            'sku' => 'nullable|string|max:100|unique:products,sku,' . $id,
            'stock_quantity' => 'nullable|integer|min:0',
            'minimum_stock_level' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'image_file' => 'nullable|file|image|max:5120',
            'image_url' => 'nullable|string',
            'remove_image' => 'nullable|boolean',
            'status' => 'nullable|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $data = $request->only([
            'name', 'category_id', 'unit', 'barcode', 'sku',
            'stock_quantity', 'minimum_stock_level', 'description', 'status'
        ]);

        if ($request->has('retail_price') || $request->has('price')) {
            $data['retail_price'] = $request->input('retail_price', $request->input('price'));
        }

        if ($request->has('wholesale_cost') || $request->has('cost_price')) {
            $data['wholesale_cost'] = $request->input('wholesale_cost', $request->input('cost_price'));
        }

        // Image Handling
        if ($request->boolean('remove_image')) {
            if ($product->image) {
                $this->storageService->deleteFile($product->image, 'product-images');
            }
            $data['image'] = null;
        } elseif ($request->hasFile('image_file')) {
            if ($product->image) {
                $this->storageService->deleteFile($product->image, 'product-images');
            }
            $data['image'] = $this->storageService->uploadFile($request->file('image_file'), 'product-images', 'products');
        } elseif ($request->has('image_url') && !empty($request->input('image_url'))) {
            $data['image'] = $request->input('image_url');
        }

        $product->update(array_filter($data, fn($v) => $v !== null));

        return $this->successResponse($product->fresh()->load('category'), 'Product updated successfully');
    }

    /**
     * Delete Product (Safe dependency check)
     */
    public function destroy(string $id): JsonResponse
    {
        $product = Product::find($id);
        if (!$product) {
            return $this->errorResponse('Product not found', 404);
        }

        $hasOrders = $product->orderItems()->exists();
        $hasMovements = $product->stockMovements()->exists();
        $hasReceipts = $product->stockReceiptItems()->exists();

        if ($hasOrders || $hasMovements || $hasReceipts) {
            // Safe archive rather than deleting historical relations
            $product->update(['status' => 'inactive']);
            $product->delete(); // Soft delete
            return $this->successResponse([
                'action' => 'archived',
                'message' => 'Product has historical orders/ledger records and was safely archived rather than permanently deleted.'
            ], 'Product archived successfully');
        }

        if ($product->image) {
            $this->storageService->deleteFile($product->image, 'product-images');
        }

        $product->forceDelete();
        return $this->successResponse(['action' => 'deleted'], 'Product permanently deleted successfully');
    }

    /**
     * Safe Bulk Delete / Archive Products
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'exists:products,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $ids = $request->input('product_ids');
        $deleted = 0;
        $archived = 0;

        foreach ($ids as $id) {
            $product = Product::find($id);
            if (!$product) continue;

            $hasOrders = $product->orderItems()->exists();
            $hasMovements = $product->stockMovements()->exists();
            $hasReceipts = $product->stockReceiptItems()->exists();

            if ($hasOrders || $hasMovements || $hasReceipts) {
                $product->update(['status' => 'inactive']);
                $product->delete();
                $archived++;
            } else {
                if ($product->image) {
                    $this->storageService->deleteFile($product->image, 'product-images');
                }
                $product->forceDelete();
                $deleted++;
            }
        }

        return $this->successResponse([
            'deleted' => $deleted,
            'archived' => $archived,
            'total' => count($ids),
        ], "Bulk action complete: {$deleted} permanently deleted, {$archived} safely archived.");
    }

    /**
     * Bulk Change Category
     */
    public function bulkCategory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'exists:products,id',
            'category_id' => 'required|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $ids = $request->input('product_ids');
        $categoryId = $request->input('category_id');

        Product::whereIn('id', $ids)->update(['category_id' => $categoryId]);

        return $this->successResponse([
            'updated_count' => count($ids),
        ], 'Category updated for selected products');
    }

    /**
     * Bulk Change Status (Active / Inactive)
     */
    public function bulkStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'exists:products,id',
            'status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $ids = $request->input('product_ids');
        $status = $request->input('status');

        Product::whereIn('id', $ids)->update(['status' => $status]);

        return $this->successResponse([
            'updated_count' => count($ids),
        ], "Status updated to '{$status}' for selected products");
    }

    /**
     * Download Excel / CSV Product Import Template
     */
    public function importTemplate()
    {
        $categories = Category::pluck('name')->implode(', ');
        
        $headers = [
            'product_name',
            'sku',
            'barcode',
            'category',
            'unit',
            'cost_price',
            'selling_price',
            'stock_quantity',
            'low_stock_threshold',
            'status',
            'description',
        ];

        $sampleRows = [
            [
                'Al Rawabi Full Cream Milk 1L',
                'MILK-1L',
                '6291001234567',
                'Dairy',
                'piece',
                '3.20',
                '4.50',
                '20',
                '5',
                'active',
                'Fresh pasteurized full cream cow milk',
            ],
            [
                'India Gate Basmati Rice 5kg',
                'RICE-5KG',
                '8901030012345',
                'Rice & Grains',
                'pack',
                '24.00',
                '32.50',
                '15',
                '3',
                'active',
                'Premium aged basmati rice',
            ],
        ];

        $output = "\xEF\xBB\xBF"; // UTF-8 BOM for Microsoft Excel compatibility
        $output .= implode(',', $headers) . "\n";
        foreach ($sampleRows as $row) {
            $output .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $row)) . "\n";
        }

        return Response::make($output, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="Baqqala_Products_Import_Template.csv"',
        ]);
    }

    /**
     * Import Products from CSV with Validation & Preview / Confirm Modes
     */
    public function import(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB max
            'confirm' => 'nullable|boolean',
            'import_stock' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $file = $request->file('file');
        $path = $file->getRealPath();
        $handle = fopen($path, 'r');
        if (!$handle) {
            return $this->errorResponse('Unable to open uploaded file', 400);
        }

        // Read header
        $rawHeader = fgetcsv($handle);
        if (!$rawHeader) {
            fclose($handle);
            return $this->errorResponse('Uploaded file is empty', 400);
        }

        // Remove BOM from first column if present
        $rawHeader[0] = preg_replace('/^\xEF\xBB\xBF/', '', $rawHeader[0]);
        $headerMap = [];
        foreach ($rawHeader as $idx => $col) {
            $headerMap[trim(strtolower($col))] = $idx;
        }

        $requiredCols = ['product_name', 'sku', 'category', 'selling_price'];
        foreach ($requiredCols as $req) {
            if (!isset($headerMap[$req])) {
                fclose($handle);
                return $this->errorResponse("Missing required column in template: '{$req}'", 422);
            }
        }

        // Cache existing categories: lowercase name -> id
        $categories = Category::all()->keyBy(fn($c) => strtolower(trim($c->name)));

        $rows = [];
        $validCount = 0;
        $newCount = 0;
        $updateCount = 0;
        $warningsCount = 0;
        $errorsCount = 0;

        $rowNum = 1;
        while (($data = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (empty(array_filter($data))) continue; // Skip empty rows

            $name = trim($data[$headerMap['product_name']] ?? '');
            $sku = trim($data[$headerMap['sku']] ?? '');
            $barcode = isset($headerMap['barcode']) ? trim($data[$headerMap['barcode']] ?? '') : '';
            $categoryName = trim($data[$headerMap['category']] ?? '');
            $unit = isset($headerMap['unit']) ? trim($data[$headerMap['unit']] ?? 'piece') : 'piece';
            $costPrice = isset($headerMap['cost_price']) ? floatval(str_replace(',', '', $data[$headerMap['cost_price']])) : 0.00;
            $sellingPrice = floatval(str_replace(',', '', $data[$headerMap['selling_price']] ?? 0));
            $stockQty = isset($headerMap['stock_quantity']) ? intval($data[$headerMap['stock_quantity']]) : 0;
            $minStock = isset($headerMap['low_stock_threshold']) ? intval($data[$headerMap['low_stock_threshold']]) : 5;
            $status = isset($headerMap['status']) ? strtolower(trim($data[$headerMap['status']])) : 'active';
            $description = isset($headerMap['description']) ? trim($data[$headerMap['description']] ?? '') : '';

            $rowStatus = 'VALID';
            $message = '';
            $action = 'CREATE';

            // Validation
            if (empty($name)) {
                $rowStatus = 'ERROR';
                $message = 'Product name is required';
            } elseif (empty($sku)) {
                $rowStatus = 'ERROR';
                $message = 'SKU is required';
            } elseif ($sellingPrice <= 0) {
                $rowStatus = 'ERROR';
                $message = 'Selling price must be greater than zero';
            } else {
                $catKey = strtolower($categoryName);
                if (!isset($categories[$catKey])) {
                    $rowStatus = 'ERROR';
                    $message = "Category '{$categoryName}' does not exist. Please create it first.";
                }
            }

            if ($rowStatus === 'VALID') {
                $existing = Product::where('sku', $sku)->first();
                if ($existing) {
                    $action = 'UPDATE';
                    $updateCount++;
                } else {
                    $action = 'CREATE';
                    $newCount++;
                }
                $validCount++;
            } else {
                $errorsCount++;
            }

            $rows[] = [
                'row_number' => $rowNum,
                'name' => $name,
                'sku' => $sku,
                'barcode' => $barcode,
                'category' => $categoryName,
                'unit' => $unit,
                'cost_price' => $costPrice,
                'selling_price' => $sellingPrice,
                'stock_quantity' => $stockQty,
                'min_stock' => $minStock,
                'status' => in_array($status, ['active', 'inactive']) ? $status : 'active',
                'description' => $description,
                'action' => $action,
                'status_code' => $rowStatus,
                'message' => $message,
            ];
        }

        fclose($handle);

        $isConfirm = $request->boolean('confirm');
        $importStock = $request->boolean('import_stock');

        // If PREVIEW mode, return inspection results
        if (!$isConfirm) {
            return $this->successResponse([
                'preview' => true,
                'total_rows' => count($rows),
                'valid_count' => $validCount,
                'new_count' => $newCount,
                'update_count' => $updateCount,
                'errors_count' => $errorsCount,
                'rows' => array_slice($rows, 0, 100), // Preview first 100 rows
            ], 'Import file validated');
        }

        // CONFIRM MODE: Execute transactional import
        if ($errorsCount > 0 && count($rows) === $errorsCount) {
            return $this->errorResponse('Cannot import file: all rows contained validation errors.', 422);
        }

        $imported = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, $categories, $importStock, &$imported, &$updated) {
            foreach ($rows as $r) {
                if ($r['status_code'] !== 'VALID') continue;

                $catId = $categories[strtolower($r['category'])]->id;

                $product = Product::where('sku', $r['sku'])->first();
                if ($product) {
                    // Update catalog data
                    $updateData = [
                        'name' => $r['name'],
                        'category_id' => $catId,
                        'unit' => $r['unit'],
                        'retail_price' => $r['selling_price'],
                        'wholesale_cost' => $r['cost_price'],
                        'minimum_stock_level' => $r['min_stock'],
                        'status' => $r['status'],
                        'description' => $r['description'] ?: $product->description,
                    ];
                    if (!empty($r['barcode'])) {
                        $updateData['barcode'] = $r['barcode'];
                    }
                    if ($importStock) {
                        $updateData['stock_quantity'] = $r['stock_quantity'];
                    }

                    $product->update($updateData);
                    $updated++;
                } else {
                    // Create new product
                    Product::create([
                        'name' => $r['name'],
                        'sku' => $r['sku'],
                        'barcode' => $r['barcode'] ?: ('629' . rand(1000000000, 9999999999)),
                        'category_id' => $catId,
                        'unit' => $r['unit'],
                        'retail_price' => $r['selling_price'],
                        'wholesale_cost' => $r['cost_price'],
                        'stock_quantity' => $r['stock_quantity'],
                        'minimum_stock_level' => $r['min_stock'],
                        'status' => $r['status'],
                        'description' => $r['description'] ?: null,
                    ]);
                    $imported++;
                }
            }
        });

        return $this->successResponse([
            'imported_new' => $imported,
            'updated_existing' => $updated,
            'total' => $imported + $updated,
        ], "Successfully imported {$imported} new products and updated {$updated} existing products.");
    }

    /**
     * Export Products to CSV respecting active search & filters
     */
    public function export(Request $request)
    {
        $query = Product::with('category');

        if ($request->has('category_id') && !empty($request->input('category_id'))) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->has('status') && !empty($request->input('status'))) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('stock_status') && !empty($request->input('stock_status'))) {
            $stockStatus = $request->input('stock_status');
            if ($stockStatus === 'out_of_stock') {
                $query->where('stock_quantity', '<=', 0);
            } elseif ($stockStatus === 'low_stock') {
                $query->where('stock_quantity', '>', 0)
                      ->whereColumn('stock_quantity', '<=', 'minimum_stock_level');
            } elseif ($stockStatus === 'in_stock') {
                $query->whereColumn('stock_quantity', '>', 'minimum_stock_level');
            }
        }

        if ($request->has('q') && !empty(trim($request->input('q')))) {
            $q = trim($request->input('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%");
            });
        }

        $products = $query->orderBy('name')->get();

        $headers = [
            'ID',
            'Product Name',
            'SKU',
            'Barcode',
            'Category',
            'Unit',
            'Wholesale Cost (AED)',
            'Retail Price (AED)',
            'Stock Quantity',
            'Min Stock Threshold',
            'Status',
            'Supplier',
            'Image URL',
        ];

        $output = "\xEF\xBB\xBF"; // UTF-8 BOM
        $output .= implode(',', $headers) . "\n";

        foreach ($products as $p) {
            $row = [
                $p->id,
                $p->name,
                $p->sku,
                $p->barcode,
                $p->category?->name ?? 'General',
                $p->unit,
                number_format((float) $p->wholesale_cost, 2),
                number_format((float) $p->retail_price, 2),
                $p->stock_quantity,
                $p->minimum_stock_level,
                $p->status,
                $p->supplier_name,
                $p->image_url,
            ];

            $output .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', (string)$v) . '"', $row)) . "\n";
        }

        $filename = 'Baqqala_Products_Export_' . date('Y-m-d') . '.csv';

        return Response::make($output, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
