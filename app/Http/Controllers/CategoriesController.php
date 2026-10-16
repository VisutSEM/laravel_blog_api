<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoriesController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::with('products')->get();

        return CategoryResource::collection($categories);
    }

    /**
     * Store a newly created category.
     */
    public function store(StoreCategoryRequest $request): CategoryResource
    {
        $category = Category::create($request->validated());

        return new CategoryResource($category);
    }

    /**
     * Display the specified category.
     */
    public function show(Category|int|string $category): CategoryResource
    {
        $model = $category instanceof Category ? $category : Category::with('products')->findOrFail($category);

        return new CategoryResource($model);
    }

    /**
     * Update the specified category.
     */
    public function update(UpdateCategoryRequest $request, Category|int|string $category): CategoryResource
    {
        $model = $category instanceof Category ? $category : Category::findOrFail($category);
        $model->update($request->validated());

        return new CategoryResource($model);
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Category|int|string $category): JsonResponse
    {
        $model = $category instanceof Category ? $category : Category::findOrFail($category);
        $model->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Category deleted successfully',
        ], 200);
    }
}
