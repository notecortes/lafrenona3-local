<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Services\InventoryStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $query = Ingredient::where('restaurant_id', $restaurantId)
            ->orderBy('name');

        if ($request->boolean('low_stock')) {
            $query->whereColumn('stock_quantity', '<=', 'min_stock')
                ->where('min_stock', '>', 0);
        }

        $ingredients = $query->get();

        return response()->json([
            'data' => $ingredients->map(fn ($ingredient) => [
                'id' => $ingredient->id,
                'restaurant_id' => $ingredient->restaurant_id,
                'name' => $ingredient->name,
                'unit' => $ingredient->unit,
                'stock_quantity' => $ingredient->stock_quantity,
                'min_stock' => $ingredient->min_stock,
                'is_low_stock' => $ingredient->isLowStock(),
                'created_at' => $ingredient->created_at,
                'updated_at' => $ingredient->updated_at,
            ]),
        ], 200);
    }

    public function adjust(Request $request): JsonResponse
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $request->validate([
            'ingredient_id' => 'required|integer|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0.001',
            'type' => 'required|in:in,out,adjustment',
            'notes' => 'nullable|string|max:1000',
        ]);

        $ingredient = Ingredient::where('id', $request->input('ingredient_id'))
            ->where('restaurant_id', $restaurantId)
            ->first();

        if ($ingredient === null) {
            return response()->json(['message' => 'Ingredient not found.'], 404);
        }

        $service = app(InventoryStockService::class);

        $adjustment = $service->addStock(
            restaurantId: $restaurantId,
            ingredientId: $ingredient->id,
            quantity: (float) $request->input('quantity'),
            type: $request->input('type'),
            notes: $request->input('notes'),
            referenceType: 'manual_adjustment',
            referenceId: null
        );

        $ingredient->refresh();

        return response()->json([
            'data' => [
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'unit' => $ingredient->unit,
                'stock_quantity' => $ingredient->stock_quantity,
                'min_stock' => $ingredient->min_stock,
                'is_low_stock' => $ingredient->isLowStock(),
                'last_adjustment' => [
                    'type' => $adjustment->adjustment_type,
                    'quantity' => $adjustment->quantity,
                    'notes' => $adjustment->notes,
                    'created_at' => $adjustment->created_at,
                ],
            ],
        ], 200);
    }
}
