<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\StoreProductRequest;
use App\Http\Requests\Owner\UpdateProductRequest;
use App\Http\Resources\Owner\CategoryResource;
use App\Http\Resources\Owner\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductsController extends Controller
{
    public function index(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $products = Product::with('category')
            ->when($request->boolean('is_active'), fn ($q) => $q->where('is_active', true))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->category_id))
            ->orderBy('is_active', 'desc')
            ->get();

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request): ProductResource
    {
        $product = Product::create([
            'restaurant_id' => $request->user()->restaurant_id,
            'category_id' => $request->input('category_id'),
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'price' => $request->input('price'),
            'weekend_price' => $request->input('weekend_price'),
            'image_url' => $request->input('image_url'),
            'stock_status' => $request->input('stock_status', 'available'),
            'is_active' => $request->input('is_active', true),
            'is_vegan' => $request->input('is_vegan', false),
            'is_vegetarian' => $request->input('is_vegetarian', false),
            'allergens' => $request->input('allergens'),
        ]);

        return new ProductResource($product);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->update([
            'category_id' => $request->input('category_id', $product->category_id),
            'name' => $request->input('name', $product->name),
            'description' => $request->input('description', $product->description),
            'price' => $request->input('price', $product->price),
            'weekend_price' => $request->input('weekend_price', $product->weekend_price),
            'image_url' => $request->input('image_url', $product->image_url),
            'stock_status' => $request->input('stock_status', $product->stock_status),
            'is_active' => $request->input('is_active', $product->is_active),
            'is_vegan' => $request->input('is_vegan', $product->is_vegan),
            'is_vegetarian' => $request->input('is_vegetarian', $product->is_vegetarian),
            'allergens' => $request->input('allergens', $product->allergens),
        ]);

        return new ProductResource($product);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['message' => 'Product deleted.'], 200);
    }

    public function categories(): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return CategoryResource::collection($categories);
    }
}
