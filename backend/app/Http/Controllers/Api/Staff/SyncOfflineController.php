<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncOfflineController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->validate([
            'operations' => 'required|array|min:1',
            'operations.*.idempotency_key' => 'required|string|max:64',
            'operations.*.type' => 'required|in:order_item_status_update,order_item_create',
            'operations.*.payload' => 'required|array',
        ]);

        $operations = $request->input('operations');
        $results = [];
        $accepted = 0;
        $duplicates = 0;
        $rejected = 0;
        $conflicts = 0;

        foreach ($operations as $op) {
            $idempotencyKey = $op['idempotency_key'];
            $type = $op['type'];
            $payload = $op['payload'];

            $existingOperation = \App\Models\OfflineOperation::where('idempotency_key', $idempotencyKey)
                ->where('restaurant_id', $user->restaurant_id)
                ->first();

            if ($existingOperation !== null) {
                $results[] = [
                    'idempotency_key' => $idempotencyKey,
                    'type' => $type,
                    'status' => 'duplicate',
                    'message' => 'Operation already processed.',
                ];
                $duplicates++;
                continue;
            }

            try {
                $operationResult = DB::transaction(function () use ($user, $idempotencyKey, $type, $payload) {
                    $restaurantId = $this->resolveRestaurantId($user, $payload);

                    if ($restaurantId === null) {
                        return ['status' => 'rejected', 'message' => 'Restaurant context not found.'];
                    }

                    $operation = \App\Models\OfflineOperation::create([
                        'restaurant_id' => $restaurantId,
                        'idempotency_key' => $idempotencyKey,
                        'type' => $type,
                        'payload' => $payload,
                        'status' => 'pending',
                    ]);

                    $result = null;

                    if ($type === 'order_item_create') {
                        $result = $this->processOrderItemCreate($restaurantId, $payload, $idempotencyKey);
                    }

                    if ($type === 'order_item_status_update') {
                        Log::debug('Processing order_item_status_update', ['restaurantId' => $restaurantId, 'payload' => $payload, 'idempotencyKey' => $idempotencyKey]);
                        $result = $this->processOrderItemStatusUpdate($restaurantId, $payload, $idempotencyKey);
                        Log::debug('Result', ['result' => $result]);
                    }

                    if ($result === null) {
                        $result = ['status' => 'rejected', 'message' => 'Unknown operation type.'];
                    }

                    $operation->update([
                        'status' => $result['status'] === 'accepted' ? 'processed' : 'failed',
                        'error_message' => $result['status'] === 'failed' ? $result['message'] : null,
                    ]);

                    return $result;
                });

                $results[] = [
                    'idempotency_key' => $idempotencyKey,
                    'type' => $type,
                    'status' => $operationResult['status'],
                    'message' => $operationResult['message'] ?? null,
                ];

                if ($operationResult['status'] === 'accepted') {
                    $accepted++;
                } elseif ($operationResult['status'] === 'rejected') {
                    $rejected++;
                } elseif ($operationResult['status'] === 'conflict') {
                    $conflicts++;
                }
            } catch (\Throwable $e) {
                Log::error('Offline sync operation failed: ' . $e->getMessage());

                \App\Models\OfflineOperation::where('idempotency_key', $idempotencyKey)
                    ->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                    ]);

                $results[] = [
                    'idempotency_key' => $idempotencyKey,
                    'type' => $type,
                    'status' => 'rejected',
                    'message' => 'Internal processing error.',
                ];
                $rejected++;
            }
        }

        return response()->json([
            'data' => [
                'total' => count($results),
                'accepted' => $accepted,
                'duplicates' => $duplicates,
                'rejected' => $rejected,
                'conflicts' => $conflicts,
                'results' => $results,
            ],
        ], 200);
    }

    private function resolveRestaurantId(\App\Models\User $user, array $payload): ?int
    {
        if (isset($payload['restaurant_id'])) {
            return (int) $payload['restaurant_id'];
        }

        if (isset($payload['order_id'])) {
            $order = Order::where('id', $payload['order_id'])
                ->where('restaurant_id', $user->restaurant_id)
                ->first();

            if ($order !== null) {
                return $order->restaurant_id;
            }
        }

        return $user->restaurant_id;
    }

    private function processOrderItemCreate(int $restaurantId, array $payload, string $idempotencyKey): array
    {
        $productId = $payload['product_id'] ?? null;
        $orderId = $payload['order_id'] ?? null;
        $quantity = $payload['quantity'] ?? 1;
        $unitPrice = $payload['unit_price'] ?? 0;
        $targetArea = $payload['target_area'] ?? 'kitchen';

        if ($productId === null || $orderId === null) {
            return ['status' => 'rejected', 'message' => 'Missing required fields.'];
        }

        $existingItem = OrderItem::where('idempotency_key', $idempotencyKey)
            ->where('restaurant_id', $restaurantId)
            ->first();

        if ($existingItem !== null) {
            return ['status' => 'duplicate', 'message' => 'Item already exists.'];
        }

        $product = Product::where('id', $productId)
            ->where('restaurant_id', $restaurantId)
            ->first();

        if ($product === null) {
            return ['status' => 'rejected', 'message' => 'Product not found.'];
        }

        $order = Order::where('id', $orderId)
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'open')
            ->first();

        if ($order === null) {
            return ['status' => 'conflict', 'message' => 'Order not found or closed.'];
        }

        OrderItem::create([
            'restaurant_id' => $restaurantId,
            'order_id' => $orderId,
            'product_id' => $productId,
            'quantity' => (int) $quantity,
            'unit_price' => (float) $unitPrice,
            'notes' => $payload['notes'] ?? null,
            'status' => $payload['status'] ?? 'pending',
            'target_area' => $targetArea,
            'idempotency_key' => $idempotencyKey,
            'price_snapshot' => (float) $unitPrice,
            'tax_rate' => 10.00,
            'discount_amount' => 0.00,
        ]);

        $order->update([
            'total_price' => round((float) $order->total_price + ((float) $unitPrice * (int) $quantity), 2),
        ]);

        return ['status' => 'accepted', 'message' => 'Order item created.'];
    }

    private function processOrderItemStatusUpdate(int $restaurantId, array $payload, string $idempotencyKey): array
    {
        $orderItemId = $payload['order_item_id'] ?? null;
        $newStatus = $payload['status'] ?? null;

        if ($orderItemId === null || $newStatus === null) {
            return ['status' => 'rejected', 'message' => 'Missing required fields.'];
        }

        $allowedTransitions = [
            'pending' => ['cooking', 'cancelled'],
            'cooking' => ['ready'],
            'ready' => ['delivered', 'cancelled'],
            'delivered' => [],
            'cancelled' => [],
        ];

        $orderItem = OrderItem::withoutGlobalScopes()->where('id', $orderItemId)
            ->where('restaurant_id', $restaurantId)
            ->first();

        Log::debug('Status update debug', [
            'order_item_id' => $orderItemId,
            'restaurant_id' => $restaurantId,
            'order_item_found' => $orderItem !== null,
            'previous_status' => $orderItem?->status,
        ]);

        if ($orderItem === null) {
            return ['status' => 'rejected', 'message' => 'Order item not found.'];
        }

        $previousStatus = $orderItem->status;
        $allowed = $allowedTransitions[$previousStatus] ?? [];

        if (! in_array($newStatus, $allowed, true)) {
            return [
                'status' => 'conflict',
                'message' => "Invalid transition from {$previousStatus} to {$newStatus}.",
            ];
        }

        $orderItem->update(['status' => $newStatus]);

        return ['status' => 'accepted', 'message' => 'Status updated.'];
    }
}
