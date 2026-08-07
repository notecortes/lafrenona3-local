<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientOrdersController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'session_token' => 'required|string|max:64',
            'restaurant_slug' => 'required|string|max:155',
        ]);

        $table = Table::where('secret_token', $request->input('session_token'))
            ->first();

        if ($table === null) {
            return response()->json(['message' => 'Invalid session token.'], 404);
        }

        $restaurant = $table->restaurant;

        if ($restaurant->status !== 'active') {
            return response()->json(['message' => 'Restaurant is currently closed.'], 403);
        }

        $idempotencyKey = (string) $request->header('X-Idempotency-Key', Str::uuid()->toString());

        $existingOrder = Order::where('table_id', $table->id)
            ->where('status', 'open')
            ->first();

        if ($existingOrder !== null) {
            return response()->json([
                'data' => [
                    'id' => $existingOrder->id,
                    'restaurant_id' => $existingOrder->restaurant_id,
                    'table_id' => $existingOrder->table_id,
                    'status' => $existingOrder->status,
                    'total_price' => $existingOrder->total_price,
                    'items' => [],
                ],
            ], 200);
        }

        $order = DB::transaction(function () use ($table, $idempotencyKey) {
            $order = Order::create([
                'restaurant_id' => $table->restaurant_id,
                'table_id' => $table->id,
                'session_token' => $table->session_token ?? Str::random(64),
                'status' => 'open',
                'total_price' => 0.00,
                'idempotency_key' => $idempotencyKey,
            ]);

            return $order;
        });

        return response()->json([
            'data' => [
                'id' => $order->id,
                'restaurant_id' => $order->restaurant_id,
                'table_id' => $order->table_id,
                'status' => $order->status,
                'total_price' => $order->total_price,
                'items' => [],
            ],
        ], 201);
    }

    public function appendItems(Request $request, Order $order): JsonResponse
    {
        $restaurant = $order->restaurant;

        if ($restaurant->status !== 'active') {
            return response()->json(['message' => 'Restaurant is currently closed.'], 403);
        }

        if ($order->status !== 'open') {
            return response()->json(['message' => 'Order is not open.'], 422);
        }

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:1000',
            'items.*.target_area' => 'nullable|in:kitchen,bar',
            'items.*.idempotency_key' => 'nullable|string|max:64',
        ]);

        $idempotencyKey = (string) $request->header('X-Idempotency-Key', Str::uuid()->toString());

        $itemsData = $request->input('items');
        $createdItems = [];
        $newTotal = (float) $order->total_price;

        $productIds = array_column($itemsData, 'product_id');
        $products = Product::whereIn('id', $productIds)
            ->where('restaurant_id', $order->restaurant_id)
            ->get()
            ->keyBy('id');

        foreach ($itemsData as $itemData) {
            $product = $products->get($itemData['product_id']);

            if ($product === null) {
                return response()->json(['message' => 'Product not found.'], 404);
            }

            $itemIdempotencyKey = $itemData['idempotency_key'] ?? $idempotencyKey . '-' . $itemData['product_id'];

            $existingItem = OrderItem::where('idempotency_key', $itemIdempotencyKey)
                ->where('restaurant_id', $order->restaurant_id)
                ->first();

            if ($existingItem !== null) {
                $createdItems[] = $existingItem;
                continue;
            }

            $unitPrice = (float) $product->price;

            $orderItem = DB::transaction(function () use ($order, $product, $itemData, $unitPrice, $itemIdempotencyKey) {
                return OrderItem::create([
                    'restaurant_id' => $order->restaurant_id,
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => (int) $itemData['quantity'],
                    'unit_price' => $unitPrice,
                    'notes' => $itemData['notes'] ?? null,
                    'status' => 'pending',
                    'target_area' => $itemData['target_area'] ?? 'kitchen',
                    'idempotency_key' => $itemIdempotencyKey,
                    'price_snapshot' => $unitPrice,
                    'tax_rate' => 10.00,
                    'discount_amount' => 0.00,
                ]);
            });

            $newTotal += $unitPrice * (int) $itemData['quantity'];
            $createdItems[] = $orderItem;
        }

        DB::transaction(function () use ($order, $newTotal) {
            $order->update([
                'total_price' => round($newTotal, 2),
            ]);
        });

        return response()->json([
            'data' => [
                'id' => $order->id,
                'restaurant_id' => $order->restaurant_id,
                'table_id' => $order->table_id,
                'status' => $order->status,
                'total_price' => $order->total_price,
                'items' => collect($createdItems)->map(fn ($item) => [
                    'id' => $item->id,
                    'order_id' => $item->order_id,
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

    public function closeOrder(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->restaurant_id !== null && $user->restaurant_id !== $order->restaurant_id) {
            return response()->json(['message' => 'Access denied. Order does not belong to your restaurant.'], 403);
        }

        if ($order->status === 'closed') {
            return response()->json(['message' => 'Order is already closed.'], 422);
        }

        $result = DB::transaction(function () use ($order) {
            $order->load(['items.product']);

            $order->update(['status' => 'closed']);

            $table = $order->table;

            if ($table !== null) {
                $table->update(['status' => 'free']);
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
