<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Api\v1\BaseApiController;
use App\Models\Product;
use App\Services\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * List Products for Admin Management
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('category');

        if ($request->has('category_id') && !empty($request->input('category_id'))) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->has('status') && !empty($request->input('status'))) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('q') && !empty(trim($request->input('q')))) {
            $q = trim($request->input('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%");
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
            'price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'minimum_stock_level' => 'nullable|integer|min:0',
            'sku' => 'nullable|string|unique:products,sku',
            'description' => 'nullable|string',
            'image_file' => 'nullable|file|image|max:5120',
            'image_url' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $imageUrl = $request->input('image_url');
        if ($request->hasFile('image_file')) {
            $imageUrl = $this->storageService->uploadFile($request->file('image_file'), 'product-images', 'products');
        }

        $sku = $request->input('sku') ?: 'SKU-' . strtoupper(Str::random(6));

        $product = Product::create([
            'name' => trim($request->input('name')),
            'category_id' => $request->input('category_id'),
            'price' => $request->input('price'),
            'sale_price' => $request->input('sale_price'),
            'stock_quantity' => $request->input('stock_quantity'),
            'minimum_stock_level' => $request->input('minimum_stock_level', 5),
            'sku' => $sku,
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
            return $this->errorResponse('Product not found', [], 404);
        }

        return $this->successResponse($product, 'Product details retrieved successfully');
    }

    /**
     * Update Existing Product
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::find($id);
        if (!$product) {
            return $this->errorResponse('Product not found', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'category_id' => 'sometimes|required|exists:categories,id',
            'price' => 'sometimes|required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'sometimes|required|integer|min:0',
            'minimum_stock_level' => 'nullable|integer|min:0',
            'sku' => 'nullable|string|unique:products,sku,' . $id,
            'description' => 'nullable|string',
            'image_file' => 'nullable|file|image|max:5120',
            'image_url' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $data = $request->only([
            'name', 'category_id', 'price', 'sale_price',
            'stock_quantity', 'minimum_stock_level', 'sku', 'description', 'status'
        ]);

        if ($request->hasFile('image_file')) {
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
     * Delete Product
     */
    public function destroy(string $id): JsonResponse
    {
        $product = Product::find($id);
        if (!$product) {
            return $this->errorResponse('Product not found', [], 404);
        }

        if ($product->image) {
            $this->storageService->deleteFile($product->image, 'product-images');
        }

        $product->delete();
        return $this->successResponse(null, 'Product deleted successfully');
    }
}
