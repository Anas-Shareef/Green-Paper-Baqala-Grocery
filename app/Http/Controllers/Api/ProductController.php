<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Get home storefront data: categories, featured products, banners, MOV settings.
     */
    public function home(): JsonResponse
    {
        $categories = Category::where('status', 'active')
            ->orderBy('sort_order')
            ->get();

        $featuredProducts = Product::with('category')
            ->where('status', 'active')
            ->orderBy('id', 'desc')
            ->limit(12)
            ->get()
            ->makeHidden(['wholesale_cost']); // Never expose wholesale cost!

        $mov = (float) BusinessSetting::get('minimum_order_value', 300.00);

        return response()->json([
            'store_name' => BusinessSetting::get('store_name', 'Baqqala'),
            'store_tagline' => BusinessSetting::get('store_tagline', 'Your Everyday Grocery, Delivered.'),
            'minimum_order_value' => $mov,
            'currency_symbol' => BusinessSetting::get('currency_symbol', '₹'),
            'categories' => $categories,
            'featured_products' => $featuredProducts,
        ]);
    }

    /**
     * Get all active products with pagination and category filter.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('category')->where('status', 'active');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('category_slug')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category_slug);
            });
        }

        $products = $query->orderBy('name')->paginate(24);
        $products->makeHidden(['wholesale_cost']);

        return response()->json($products);
    }

    /**
     * Search products by name, barcode, brand, or description.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->input('q', ''));

        if (empty($q)) {
            return response()->json([]);
        }

        $products = Product::with('category')
            ->where('status', 'active')
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%")
                    ->orWhere('brand', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            })
            ->limit(30)
            ->get()
            ->makeHidden(['wholesale_cost']);

        return response()->json($products);
    }

    /**
     * Get single product details.
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::with('category')
            ->where('status', 'active')
            ->findOrFail($id);

        $product->makeHidden(['wholesale_cost']);

        return response()->json($product);
    }

    /**
     * Get all categories.
     */
    public function categories(): JsonResponse
    {
        $categories = Category::where('status', 'active')
            ->orderBy('sort_order')
            ->get();

        return response()->json($categories);
    }
}
