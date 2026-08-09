<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffRoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $tables = Table::where('restaurant_id', $restaurantId)
            ->with(['orders' => function ($q) {
                $q->where('status', 'open')->with('items.product');
            }])
            ->orderBy('number')
            ->get();

        return response()->json([
            'data' => $tables->map(function ($table) {
                $activeOrder = $table->orders->first();
                $orderData = null;

                if ($activeOrder !== null) {
                    $orderData = [
                        'id' => $activeOrder->id,
                        'status' => $activeOrder->status,
                        'total_price' => (float) $activeOrder->total_price,
                        'created_at' => $activeOrder->created_at?->toIso8601String(),
                        'items' => $activeOrder->items->map(fn ($item) => [
                            'id' => $item->id,
                            'product_id' => $item->product_id,
                            'name' => is_array($item->product?->name) ? ($item->product->name['es'] ?? reset($item->product->name)) : ($item->product?->name ?? 'Producto'),
                            'quantity' => $item->quantity,
                            'unit_price' => (float) $item->unit_price,
                            'status' => $item->status,
                            'target_area' => $item->target_area,
                            'notes' => $item->notes,
                        ]),
                    ];
                }

                return [
                    'id' => $table->id,
                    'number' => $table->number,
                    'status' => $table->status,
                    'capacity' => $table->capacity,
                    'assistance_status' => $table->assistance_status === 'none' ? null : $table->assistance_status,
                    'assistance_requested_at' => $table->assistance_requested_at?->toIso8601String(),
                    'restaurant_id' => $table->restaurant_id,
                    'current_order' => $orderData,
                ];
            }),
        ], 200);
    }

    public function dismissAssistance(Request $request, Table $table): JsonResponse
    {
        $user = $request->user();

        if ($table->restaurant_id !== $user->restaurant_id) {
            return response()->json(['message' => 'Unauthorized table access.'], 403);
        }

        $table->update([
            'assistance_status' => 'none',
            'assistance_requested_at' => null,
        ]);

        return response()->json([
            'message' => 'Assistance request dismissed.',
            'data' => [
                'id' => $table->id,
                'number' => $table->number,
                'status' => $table->status,
                'assistance_status' => null,
                'assistance_requested_at' => null,
            ],
        ], 200);
    }

    public function updateStatus(Request $request, Table $table): JsonResponse
    {
        $user = $request->user();

        if ($table->restaurant_id !== $user->restaurant_id) {
            return response()->json(['message' => 'Unauthorized table access.'], 403);
        }

        $request->validate([
            'status' => 'required|in:free,occupied,payment_pending',
        ]);

        $table->update(['status' => $request->input('status')]);

        return response()->json([
            'message' => 'Table status updated.',
            'data' => [
                'id' => $table->id,
                'status' => $table->status,
            ],
        ], 200);
    }
}
