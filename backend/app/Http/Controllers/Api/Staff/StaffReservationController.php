<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\ReservationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffReservationController extends Controller
{
    public function seat(Request $request, Reservation $reservation): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->restaurant_id !== $reservation->restaurant_id) {
            return response()->json(['message' => 'Reservation not found.'], 404);
        }

        $request->validate([
            'table_id' => 'required|integer|exists:tables,id',
        ]);

        $tableId = $request->input('table_id');

        try {
            $engine = app(ReservationEngine::class);
            $seatedReservation = $engine->seatReservation($reservation->id, $tableId);

            return response()->json([
                'data' => [
                    'id' => $seatedReservation->id,
                    'status' => $seatedReservation->status,
                    'table_id' => $seatedReservation->table_id,
                ],
            ], 200);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
