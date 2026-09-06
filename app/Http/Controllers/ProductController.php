<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Cloudinary\Cloudinary;
class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::with('products')->get();
       // $product = Product::all();
       $featued_product = $categories->flatMap->product->take(5);
        return response()->json([
            'categories' => $categories,
            'featured_products' => ProductResource::collection($featued_product)
        ], 200);

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    //  public function store(Request $request)
    // {
    //     try {
    //         // Validate request
    //         $validated = $request->validate([
    //             'category_id' => 'required|exists:categories,id',
    //             'name' => 'required|string|max:255',
    //             'slug' => 'nullable|string|unique:products,slug',
    //             'description' => 'nullable|string',
    //             'price' => 'required|numeric|min:0',
    //             'image' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:2048',

    //         ]);

    //         // Upload image if exists
    //         $imagePath = null;

    //         if ($request->hasFile('image')) {
    //             $imagePath = $request->file('image')->store('products', 'public');
    //         }

    //         // Generate slug if not provided
    //         $slug = $request->slug ?? Str::slug($request->name);
    //         // Create product
    //         $product = Product::create([
    //             'category_id' => $request->category_id,
    //             'name' => $request->name,
    //             'slug' => $slug,
    //             'description' => $request->description,
    //             'price' => $request->price,
    //             'image' => $imagePath,

    //         ]);

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Product added successfully',
    //             'data' => $product
    //         ], 201);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to create product',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
   public function store(StoreProductRequest $request)
{
    $data = $request->validated();

    if ($request->hasFile('image')) {

        $cloudinary = new Cloudinary(
            env('CLOUDINARY_URL')
        );

        $result = $cloudinary->uploadApi()->upload(
            $request->file('image')->getRealPath(),
            [
                'folder' => 'products',
            ]
        );

        $data['image'] = $result['secure_url'];
    }

    $product = Product::create($data);

    return new ProductResource($product);
}
    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        //
    }  

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
         try {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        // Upload new image
        if ($request->hasFile('image')) {

            // Delete old image
            if ($product->image && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }

            // Store new image
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($validated);

        return response()->json([
            'status' => 200,
            'message' => 'Product updated successfully',
            'product' => $product,
        ], 200);

    } catch (Exception $e) {

        Log::error('Product update failed', [
            'product_id' => $product->id,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'status' => 500,
            'message' => 'Something went wrong while updating the product.',
        ], 500);
    }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        try {
        if ($product->image && Storage::disk('public')->exists($product->image)) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Product deleted successfully',
        ]);
    } catch (Exception $e) {
        Log::error('Product deletion failed', [
            'product_id' => $product->id,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'status' => 500,
            'message' => 'Something went wrong while deleting the product.',
        ], 500);
    }
    }
}
