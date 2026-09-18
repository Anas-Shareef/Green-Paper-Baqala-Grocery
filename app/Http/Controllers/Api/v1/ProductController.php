<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends BaseApiController
{
    /**
     * Storefront Homepage Feed (Categories + Featured Products)
     */
    public function home(): JsonResponse
    {
        $cacheKey = 'storefront_home_feed_v1';
        $data = Cache::remember($cacheKey, 60, function () {
            $categories = Category::where('status', 'active')
                ->select('id', 'name', 'slug', 'image', 'sort_order')
                ->orderBy('sort_order', 'asc')
                ->get();

            $featuredProducts = Product::where('status', 'active')
                ->select('id', 'category_id', 'barcode', 'sku', 'name', 'brand', 'unit', 'retail_price', 'stock_quantity', 'image')
                ->with('category:id,name,slug')
                ->orderBy('id', 'desc')
                ->take(12)
                ->get();

            return [
                'categories' => $categories,
                'featured_products' => $featuredProducts,
            ];
        });

        return $this->successResponse($data, 'Home feed retrieved successfully');
    }

    /**
     * Get All Active Products (Paginated & Filterable)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::where('status', 'active')
            ->select('id', 'category_id', 'barcode', 'sku', 'name', 'brand', 'unit', 'retail_price', 'stock_quantity', 'image')
            ->with('category:id,name,slug');

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
                  ->orWhere('barcode', 'like', "%{$search}%");
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
            ->select('id', 'category_id', 'barcode', 'sku', 'name', 'brand', 'unit', 'retail_price', 'stock_quantity', 'image')
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('sku', 'like', "%{$q}%")
                      ->orWhere('barcode', 'like', "%{$q}%");
            })
            ->with('category:id,name,slug')
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
                $q->where('id', $id)->orWhere('sku', $id)->orWhere('barcode', $id);
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
        $categories = Cache::remember('storefront_categories_v1', 120, function () {
            return Category::where('status', 'active')
                ->select('id', 'name', 'slug', 'image', 'sort_order')
                ->orderBy('sort_order', 'asc')
                ->get();
        });

        return $this->successResponse($categories, 'Categories retrieved successfully');
    }
}
