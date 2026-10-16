<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Cloudinary\Cloudinary;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of products and categories.
     * Supports filtering by category_id, search keyword, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('category')->latest();

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('featured')) {
            $query->where('is_featured', true);
        }

        // If client requests list directly (search/filter/pagination)
        if ($request->has('category_id') || $request->has('search') || $request->has('page') || $request->boolean('products_only')) {
            $products = $query->paginate($request->integer('per_page', 15));

            return response()->json([
                'status' => 'success',
                'data'   => $products,
            ], 200);
        }

        // Default home view: return categories with products & featured products
        $categories = Category::with('products')->get();
        $featuredProducts = Product::where('is_featured', true)
            ->latest()
            ->take(8)
            ->get();

        if ($featuredProducts->isEmpty()) {
            $featuredProducts = Product::latest()->take(8)->get();
        }

        return response()->json([
            'status'            => 'success',
            'categories'        => $categories,
            'featured_products' => ProductResource::collection($featuredProducts),
            'products'          => ProductResource::collection(Product::latest()->take(20)->get()),
        ], 200);
    }

    /**
     * Store a newly created product.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // Generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name']) . '-' . Str::random(4);
            }

            // Upload image to Cloudinary if provided
            if ($request->hasFile('image')) {
                $cloudinaryUrl = config('services.cloudinary.url', env('CLOUDINARY_URL'));
                $cloudinary = new Cloudinary($cloudinaryUrl);

                $result = $cloudinary
                    ->uploadApi()
                    ->upload(
                        $request->file('image')->getRealPath(),
                        ['folder' => 'products']
                    );

                $data['image'] = $result['secure_url'];
            }

            $product = Product::create($data);

            return response()->json([
                'success' => true,
                'status'  => 'success',
                'message' => 'Product added successfully',
                'data'    => new ProductResource($product->load('category')),
            ], 201);

        } catch (Exception $e) {
            Log::error('Product creation failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => 'Failed to create product: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified product.
     */
    public function show(Product|int|string $product): JsonResponse
    {
        $productModel = $product instanceof Product ? $product : Product::with('category')->findOrFail($product);

        return response()->json([
            'success' => true,
            'status'  => 'success',
            'data'    => new ProductResource($productModel->loadMissing('category')),
        ], 200);
    }

    /**
     * Update the specified product (includes stock support).
     */
    public function update(Request $request, Product|int|string $product): JsonResponse
    {
        $productModel = $product instanceof Product ? $product : Product::findOrFail($product);

        try {
            $validated = $request->validate([
                'category_id' => 'sometimes|required|exists:categories,id',
                'name'        => 'sometimes|required|string|max:255',
                'slug'        => 'nullable|string|max:255|unique:products,slug,' . $productModel->id,
                'price'       => 'sometimes|required|numeric|min:0',
                'stock'       => 'sometimes|required|integer|min:0',
                'description' => 'nullable|string',
                'is_featured' => 'nullable|boolean',
                'image'       => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            ]);

            if (isset($validated['name']) && empty($validated['slug'])) {
                $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(4);
            }

            if ($request->hasFile('image')) {
                $cloudinaryUrl = config('services.cloudinary.url', env('CLOUDINARY_URL'));
                $cloudinary = new Cloudinary($cloudinaryUrl);

                $result = $cloudinary
                    ->uploadApi()
                    ->upload(
                        $request->file('image')->getRealPath(),
                        ['folder' => 'products']
                    );

                $validated['image'] = $result['secure_url'];
            }

            $productModel->update($validated);
            $productModel->refresh();

            return response()->json([
                'success' => true,
                'status'  => 'success',
                'message' => 'Product updated successfully',
                'data'    => new ProductResource($productModel->load('category')),
            ], 200);

        } catch (Exception $e) {
            Log::error('Product update failed', [
                'product_id' => $productModel->id,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => 'Something went wrong while updating the product: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Product|int|string $product): JsonResponse
    {
        $productModel = $product instanceof Product ? $product : Product::findOrFail($product);

        try {
            $productModel->delete();

            return response()->json([
                'success' => true,
                'status'  => 'success',
                'message' => 'Product deleted successfully',
            ], 200);

        } catch (Exception $e) {
            Log::error('Product deletion failed', [
                'product_id' => $productModel->id,
                'error'      => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => 'Something went wrong while deleting the product.',
            ], 500);
        }
    }
}