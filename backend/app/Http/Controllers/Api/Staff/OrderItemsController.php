<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Events\OrderStateChanged;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderItemsController extends Controller
{
    private array $allowedTransitions = [
        'pending' => ['cooking', 'cancelled'],
        'cooking' => ['ready'],
        'ready' => ['delivered', 'cancelled'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public function updateStatus(Request $request, OrderItem $orderItem): JsonResponse
    {
        $user = $request->user();

        if ($user->restaurant_id !== $orderItem->order->restaurant_id) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        $request->validate([
            'status' => 'required|in:pending,cooking,ready,delivered,cancelled',
        ]);

        $newStatus = $request->input('status');
        $previousStatus = $orderItem->status;

        $allowed = $this->allowedTransitions[$previousStatus] ?? [];

        if (! in_array($newStatus, $allowed, true)) {
            return response()->json([
                'message' => "Invalid transition from {$previousStatus} to {$newStatus}.",
            ], 422);
        }

        DB::transaction(function () use ($orderItem, $newStatus, $previousStatus) {
            $orderItem->update(['status' => $newStatus]);

            event(new OrderStateChanged($orderItem, $previousStatus));
        });

        return response()->json([
            'message' => 'Status updated.',
            'data' => [
                'id' => $orderItem->id,
                'status' => $orderItem->status,
                'previous_status' => $previousStatus,
                'updated_at' => $orderItem->updated_at,
            ],
        ]);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.status' => 'required|in:pending,cooking,ready,delivered,cancelled',
        ]);

        $updated = 0;
        $failed = 0;

        foreach ($request->input('items') as $itemData) {
            $orderItem = OrderItem::where('id', $itemData['id'])
                ->whereHas('order', fn ($q) => $q->where('restaurant_id', $user->restaurant_id))
                ->first();

            if (! $orderItem) {
                $failed++;
                continue;
            }

            $previousStatus = $orderItem->status;
            $newStatus = $itemData['status'];

            $allowed = $this->allowedTransitions[$previousStatus] ?? [];

            if (! in_array($newStatus, $allowed, true)) {
                $failed++;
                continue;
            }

            DB::transaction(function () use ($orderItem, $newStatus, $previousStatus) {
                $orderItem->update(['status' => $newStatus]);
                event(new OrderStateChanged($orderItem, $previousStatus));
            });

            $updated++;
        }

        return response()->json([
            'message' => 'Bulk update completed.',
            'updated' => $updated,
            'failed' => $failed,
        ]);
    }

    public function pendingItems(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $user = $request->user();
        $targetArea = $request->query('area', 'kitchen');

        $items = OrderItem::with(['order.table', 'product'])
            ->where('restaurant_id', $user->restaurant_id)
            ->where('target_area', $targetArea)
            ->whereIn('status', ['pending', 'cooking', 'ready'])
            ->orderBy('created_at', 'asc')
            ->get();

        return \App\Http\Resources\Staff\OrderItemResource::collection($items);
    }
}
