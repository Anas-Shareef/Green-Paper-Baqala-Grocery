<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends BaseApiController
{
    /**
     * Storefront Homepage Feed (Categories + Featured Products)
     */
    public function home(): JsonResponse
    {
        $categories = Category::where('status', 'active')
            ->orderBy('sort_order', 'asc')
            ->get();

        $featuredProducts = Product::where('status', 'active')
            ->with('category')
            ->orderBy('id', 'desc')
            ->take(12)
            ->get();

        return $this->successResponse([
            'categories' => $categories,
            'featured_products' => $featuredProducts,
        ], 'Home feed retrieved successfully');
    }

    /**
     * Get All Active Products (Paginated & Filterable)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::where('status', 'active')->with('category');

        if ($request->has('category_id') && !empty($request->input('category_id'))) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->has('category_slug') && !empty($request->input('category_slug'))) {
            $slug = $request->input('category_slug');
            $query->whereHas('category', function ($q) use ($slug) {
                $q->where('slug', $slug);
            });
        }

        if ($request->has('q') && !empty(trim($request->input('q')))) {
            $search = trim($request->input('q'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 24), 100);
        $products = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->paginatedResponse($products, 'Products retrieved successfully');
    }

    /**
     * Search Products
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->input('q', ''));
        if (empty($q)) {
            return $this->successResponse([], 'Empty search query');
        }

        $products = Product::where('status', 'active')
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('sku', 'like', "%{$q}%")
                      ->orWhere('description', 'like', "%{$q}%");
            })
            ->with('category')
            ->take(20)
            ->get();

        return $this->successResponse($products, 'Search results retrieved successfully');
    }

    /**
     * Show Single Product Details
     */
    public function show(string $id): JsonResponse
    {
        $product = Product::where('status', 'active')
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('sku', $id);
            })
            ->with('category')
            ->first();

        if (!$product) {
            return $this->errorResponse('Product not found', [], 404);
        }

        return $this->successResponse($product, 'Product details retrieved successfully');
    }

    /**
     * Get All Active Categories
     */
    public function categories(): JsonResponse
    {
        $categories = Category::where('status', 'active')
            ->orderBy('sort_order', 'asc')
            ->get();

        return $this->successResponse($categories, 'Categories retrieved successfully');
    }
}
