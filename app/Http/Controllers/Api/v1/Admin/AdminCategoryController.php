<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Api\v1\BaseApiController;
use App\Models\Category;
use App\Services\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminCategoryController extends BaseApiController
{
    protected SupabaseStorageService $storageService;

    public function __construct(SupabaseStorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    public function index(): JsonResponse
    {
        $categories = Category::withCount('products')->orderBy('sort_order', 'asc')->get();
        return $this->successResponse($categories, 'Categories retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:categories,slug',
            'image_file' => 'nullable|file|image|max:5120',
            'image_url' => 'nullable|string',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'status' => 'nullable|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $imageUrl = $request->input('image_url');
        if ($request->hasFile('image_file')) {
            $imageUrl = $this->storageService->uploadFile($request->file('image_file'), 'category-images', 'categories');
        }

        $category = Category::create([
            'name' => trim($request->input('name')),
            'slug' => $request->input('slug') ? Str::slug($request->input('slug')) : Str::slug($request->input('name')),
            'image' => $imageUrl,
            'description' => $request->input('description'),
            'sort_order' => $request->input('sort_order', 0),
            'status' => $request->input('status', 'active'),
        ]);

        return $this->successResponse($category, 'Category created successfully', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $category = Category::find($id);
        if (!$category) {
            return $this->errorResponse('Category not found', [], 404);
        }

        $data = $request->only(['name', 'description', 'sort_order', 'status']);
        if ($request->has('name')) {
            $data['slug'] = Str::slug($request->input('name'));
        }

        if ($request->boolean('remove_image')) {
            if ($category->image) {
                $this->storageService->deleteFile($category->image, 'category-images');
            }
            $data['image'] = null;
        } elseif ($request->hasFile('image_file')) {
            if ($category->image) {
                $this->storageService->deleteFile($category->image, 'category-images');
            }
            $data['image'] = $this->storageService->uploadFile($request->file('image_file'), 'category-images', 'categories');
        } elseif ($request->has('image_url')) {
            $data['image'] = $request->input('image_url');
        }

        $category->update(array_filter($data, fn($v) => $v !== null));

        return $this->successResponse($category->fresh(), 'Category updated successfully');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $category = Category::find($id);
        if (!$category) {
            return $this->errorResponse('Category not found', 404);
        }

        $productsCount = $category->products()->count();
        if ($productsCount > 0) {
            if ($request->has('move_to_category_id') && !empty($request->input('move_to_category_id'))) {
                $targetId = (int) $request->input('move_to_category_id');
                if ($targetId === $category->id || !Category::where('id', $targetId)->exists()) {
                    return $this->errorResponse('Invalid target category for moving products', 422);
                }
                $category->products()->update(['category_id' => $targetId]);
            } elseif ($request->boolean('archive')) {
                $category->update(['status' => 'inactive']);
                return $this->successResponse([
                    'action' => 'archived',
                    'products_count' => $productsCount,
                ], "Category archived. {$productsCount} products remain assigned.");
            } else {
                return $this->errorResponse("Category contains {$productsCount} products. Please reassign products to another category or choose to archive the category.", 422, [
                    'products_count' => $productsCount,
                    'requires_action' => true,
                ]);
            }
        }

        if ($category->image) {
            $this->storageService->deleteFile($category->image, 'category-images');
        }

        $category->delete();
        return $this->successResponse(['action' => 'deleted'], 'Category deleted successfully');
    }
}
