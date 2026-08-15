<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Http\Resources\CategoryResource;
use App\Http\Requests\UpdateCategoryRequest;

class CategoriesContrller extends Controller
{
    public function store(StoreCategoryRequest $request)
    {
        $validated = $request->validated();
        $category = Category::create($validated);
        return new CategoryResource($category);
    }

    public function index()
    {
        $categories = Category::all();
        return CategoryResource::collection($categories);
        // $categories = Category::with('products')->get();

        // return response()->json([
        //     'data' => $categories
        // ]);
    }

    public function show($id)
    {
        $category = Category::findOrFail($id);

        return new CategoryResource($category);
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {

        $category->update($request->validated());
        //dd($category);
        return new CategoryResource($category);
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return response()->json([
            'message' => 'Category deleted successfully'
        ]);
    }
}
