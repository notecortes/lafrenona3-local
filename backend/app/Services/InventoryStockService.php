<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ingredient;
use App\Models\InventoryAdjustment;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryStockService
{
    public function deductStock(OrderItem $orderItem): bool
    {
        $product = Product::with('ingredients')->find($orderItem->product_id);

        if ($product === null) {
            return false;
        }

        if ($product->ingredients->isEmpty()) {
            return true;
        }

        try {
            DB::transaction(function () use ($product, $orderItem) {
                foreach ($product->ingredients as $ingredient) {
                    $quantityRequired = (float) $ingredient->pivot->quantity_required * (int) $orderItem->quantity;

                    $stock = Ingredient::where('id', $ingredient->id)
                        ->where('restaurant_id', $orderItem->restaurant_id)
                        ->first();

                    if ($stock === null) {
                        continue;
                    }

                    if ($stock->stock_quantity < $quantityRequired) {
                        continue;
                    }

                    $stock->stock_quantity = (float) $stock->stock_quantity - $quantityRequired;
                    $stock->save();

                    InventoryAdjustment::create([
                        'restaurant_id' => $orderItem->restaurant_id,
                        'ingredient_id' => $stock->id,
                        'adjustment_type' => 'out',
                        'quantity' => $quantityRequired,
                        'reference_type' => 'order_item',
                        'reference_id' => $orderItem->id,
                        'notes' => "Deducted for order item #{$orderItem->id}",
                    ]);
                }
                
                return true;
            });

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function addStock(
        int $restaurantId,
        int $ingredientId,
        float $quantity,
        string $type,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): InventoryAdjustment {
        return DB::transaction(function () use ($restaurantId, $ingredientId, $quantity, $type, $notes, $referenceType, $referenceId) {
            $ingredient = Ingredient::where('id', $ingredientId)
                ->where('restaurant_id', $restaurantId)
                ->lockForUpdate()
                ->first();

            if ($ingredient === null) {
                throw new \RuntimeException('Ingredient not found.');
            }

            if ($type === 'in') {
                $ingredient->increment('stock_quantity', $quantity);
            } elseif ($type === 'out') {
                if ($ingredient->stock_quantity < $quantity) {
                    throw new \RuntimeException('Insufficient stock.');
                }

                $ingredient->decrement('stock_quantity', $quantity);
            }

            $adjustment = InventoryAdjustment::create([
                'restaurant_id' => $restaurantId,
                'ingredient_id' => $ingredientId,
                'adjustment_type' => $type,
                'quantity' => $quantity,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
            ]);

            return $adjustment;
        });
    }
}
