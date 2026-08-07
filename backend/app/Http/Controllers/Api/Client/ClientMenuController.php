<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClientMenuController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $restaurant = $this->resolveRestaurant($request);

        if (! $restaurant) {
            return response()->json(['message' => 'Restaurant not found.'], 404);
        }

        if ($restaurant->status !== 'active') {
            return response()->json(['message' => 'Restaurant is currently closed.'], 403);
        }

        $cacheKey = "menu.{$restaurant->id}";

        $categories = Cache::remember("categories.{$cacheKey}", 900, function () use ($restaurant) {
            return Category::withoutGlobalScopes()
                ->where('restaurant_id', $restaurant->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        });

        $products = Cache::remember("products.{$cacheKey}", 900, function () use ($restaurant) {
            return Product::withoutGlobalScopes()
                ->where('restaurant_id', $restaurant->id)
                ->where('is_available', true)
                ->with(['category' => fn ($q) => $q->select('id', 'restaurant_id', 'name')])
                ->get();
        });

        $sessionToken = null;
        $tableNumber = null;

        $tokenParam = $request->query('token');

        if ($tokenParam) {
            $table = Table::where('restaurant_id', $restaurant->id)
                ->where('secret_token', $tokenParam)
                ->first();

            if ($table) {
                DB::transaction(function () use ($table, $restaurant) {
                    $table->update([
                        'status' => 'occupied',
                        'session_token' => Str::random(64),
                        'seated_at' => now(),
                    ]);
                });

                $sessionToken = $table->session_token;
                $tableNumber = $table->number;
            }
        }

        return response()->json([
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
            ],
            'categories' => $categories->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'description' => $cat->description ?? null,
                'sort_order' => $cat->sort_order,
            ]),
            'products' => $products->map(fn ($product) => [
                'id' => $product->id,
                'category_id' => $product->category_id,
                'name' => $product->name,
                'description' => $product->description ?? null,
                'price' => $product->price,
                'allergens' => $product->allergens ?? [],
                'is_available' => $product->is_available,
            ]),
            'session_token' => $sessionToken,
            'table_number' => $tableNumber,
        ]);
    }

    private function resolveRestaurant(Request $request): ?Restaurant
    {
        $slug = $request->query('restaurant');

        if (! $slug) {
            return null;
        }

        return Restaurant::where('slug', $slug)->first();
    }
}
