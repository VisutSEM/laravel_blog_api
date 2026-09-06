<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Cloudinary\Cloudinary;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of the products.
     */
    public function index()
    {
        $categories = Category::with('products')->get();

        $featuredProducts = $categories
            ->flatMap->products
            ->take(5);

        return response()->json([
            'categories' => $categories,
            'featured_products' => ProductResource::collection($featuredProducts),
        ], 200);
    }

    /**
     * Store a newly created product.
     */
    public function store(StoreProductRequest $request)
    {
        try {
            $data = $request->validated();

            // Generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }

            /*
             * Upload image to Cloudinary
             */
            if ($request->hasFile('image')) {
                $cloudinary = new Cloudinary();

                $result = $cloudinary
                    ->uploadApi()
                    ->upload(
                        $request->file('image')->getRealPath(),
                        [
                            'folder' => 'products',
                        ]
                    );

                // Save Cloudinary secure URL
                $data['image'] = $result['secure_url'];
            }

            $product = Product::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Product added successfully',
                'data' => new ProductResource($product),
            ], 201);

        } catch (Exception $e) {

            Log::error('Product creation failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product)
    {
        return response()->json([
            'success' => true,
            'data' => new ProductResource($product),
        ], 200);
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product)
    {
        //
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, Product $product)
    {
        try {

            $validated = $request->validate([
                'category_id' => 'sometimes|required|exists:categories,id',
                'name' => 'sometimes|required|string|max:255',
                'slug' => 'nullable|string|max:255|unique:products,slug,' . $product->id,
                'price' => 'sometimes|required|numeric|min:0',
                'description' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            ]);

            // Generate slug if name changed and slug wasn't provided
            if (
                isset($validated['name']) &&
                empty($validated['slug'])
            ) {
                $validated['slug'] = Str::slug($validated['name']);
            }

            /*
             * Upload new image to Cloudinary
             */
            if ($request->hasFile('image')) {

                $cloudinary = new Cloudinary();

                $result = $cloudinary
                    ->uploadApi()
                    ->upload(
                        $request->file('image')->getRealPath(),
                        [
                            'folder' => 'products',
                        ]
                    );

                $validated['image'] = $result['secure_url'];
            }

            $product->update($validated);

            // Refresh model
            $product->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => new ProductResource($product),
            ], 200);

        } catch (Exception $e) {

            Log::error('Product update failed', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while updating the product.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Product $product)
    {
        try {

            /*
             * Delete image from Cloudinary
             *
             * This requires the Cloudinary public_id.
             * If your database only stores the secure_url,
             * we cannot reliably delete the Cloudinary asset here.
             *
             * Therefore, delete the database record first.
             */

            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully',
            ], 200);

        } catch (Exception $e) {

            Log::error('Product deletion failed', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while deleting the product.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}