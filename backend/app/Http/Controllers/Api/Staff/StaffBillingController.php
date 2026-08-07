<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Events\OrderStateChanged;
use App\Events\TableCleared;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffBillingController extends Controller
{
    public function closeOrder(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->restaurant_id !== $order->restaurant_id) {
            return response()->json(['message' => 'Access denied. Order does not belong to your restaurant.'], 403);
        }

        if ($order->status === 'closed') {
            return response()->json(['message' => 'Order is already closed.'], 422);
        }

        $result = DB::transaction(function () use ($order, $user) {
            $order->load(['items.product']);

            $order->update(['status' => 'closed']);

            $table = $order->table;

            if ($table !== null) {
                $table->update(['status' => 'free']);
                event(new TableCleared($table->fresh()));
            }

            foreach ($order->items as $orderItem) {
                $previousStatus = $orderItem->status;

                if ($previousStatus !== 'delivered' && $previousStatus !== 'cancelled') {
                    $orderItem->update(['status' => 'delivered']);
                    event(new OrderStateChanged($orderItem, $previousStatus));
                }
            }

            return [
                'order' => $order->fresh(),
                'table' => $table !== null ? $table->fresh() : null,
            ];
        });

        return response()->json([
            'data' => [
                'id' => $result['order']->id,
                'restaurant_id' => $result['order']->restaurant_id,
                'table_id' => $result['order']->table_id,
                'status' => $result['order']->status,
                'total_price' => $result['order']->total_price,
                'items' => $result['order']->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => is_array($item->product->name)
                        ? ($item->product->name['en'] ?? $item->product->name[array_keys($item->product->name)[0]] ?? '')
                        : $item->product->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'price_snapshot' => $item->price_snapshot,
                    'tax_rate' => $item->tax_rate,
                    'discount_amount' => $item->discount_amount,
                    'notes' => $item->notes,
                    'status' => $item->status,
                    'target_area' => $item->target_area,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ]),
            ],
        ], 200);
    }
}
