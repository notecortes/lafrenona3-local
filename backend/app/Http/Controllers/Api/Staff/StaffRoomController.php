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
            ->select(['id', 'number', 'status', 'assistance_status', 'assistance_requested_at', 'restaurant_id'])
            ->orderBy('number')
            ->get();

        return response()->json([
            'data' => $tables->map(fn ($table) => [
                'id' => $table->id,
                'number' => $table->number,
                'status' => $table->status,
                'assistance_status' => $table->assistance_status,
                'assistance_requested_at' => $table->assistance_requested_at?->toIso8601String(),
                'restaurant_id' => $table->restaurant_id,
            ]),
        ], 200);
    }
}
