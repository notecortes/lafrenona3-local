<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\StoreCategoryRequest;
use App\Http\Requests\Owner\UpdateCategoryRequest;
use App\Http\Resources\Owner\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoriesController extends Controller
{
    public function index(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $categories = Category::orderBy('sort_order')
            ->when($request->boolean('is_active'), fn ($q) => $q->where('is_active', true))
            ->get();

        return CategoryResource::collection($categories);
    }

    public function store(StoreCategoryRequest $request): CategoryResource
    {
        $category = Category::create([
            'restaurant_id' => $request->user()->restaurant_id,
            'name' => $request->input('name'),
            'sort_order' => $request->input('sort_order', 0),
            'is_active' => $request->input('is_active', true),
        ]);

        return new CategoryResource($category);
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category);
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $oldValues = [
            'name' => $category->name,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
        ];

        $category->update([
            'name' => $request->input('name'),
            'sort_order' => $request->input('sort_order', $category->sort_order),
            'is_active' => $request->input('is_active', $category->is_active),
        ]);

        app(\App\Services\AuditLogger::class)->log(
            action: 'category_updated',
            subjectType: 'Category',
            subjectId: $category->id,
            oldValues: $oldValues,
            newValues: [
                'name' => $category->name,
                'sort_order' => $category->sort_order,
                'is_active' => $category->is_active,
            ],
            userId: $request->user()?->id,
            restaurantId: $category->restaurant_id
        );

        return new CategoryResource($category);
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json(['message' => 'Category deleted.'], 200);
    }
}
