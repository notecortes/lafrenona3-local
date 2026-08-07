<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\ReservationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientReservationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'restaurant_slug' => 'required|string|max:155',
            'customer_name' => 'required|string|max:100',
            'customer_email' => 'required|email|max:100',
            'customer_phone' => 'required|string|max:20',
            'party_size' => 'required|integer|min:1',
            'reservation_date' => 'required|date',
            'reservation_time' => 'required|date_format:H:i',
            'notes' => 'nullable|string|max:500',
        ]);

        $slug = $request->input('restaurant_slug');
        $restaurant = \App\Models\Restaurant::where('slug', $slug)->first();

        if ($restaurant === null || $restaurant->status !== 'active') {
            return response()->json(['message' => 'Restaurant not found or closed.'], 404);
        }

        $engine = app(ReservationEngine::class);

        $table = $engine->findAvailableTable(
            $restaurant->id,
            (int) $request->input('party_size'),
            $request->input('reservation_date'),
            $request->input('reservation_time')
        );

        if ($table !== null) {
            $reservation = Reservation::create([
                'restaurant_id' => $restaurant->id,
                'customer_name' => $request->input('customer_name'),
                'customer_email' => $request->input('customer_email'),
                'customer_phone' => $request->input('customer_phone'),
                'party_size' => (int) $request->input('party_size'),
                'reservation_date' => $request->input('reservation_date'),
                'reservation_time' => $request->input('reservation_time'),
                'status' => 'confirmed',
                'table_id' => $table->id,
                'notes' => $request->input('notes'),
            ]);

            return response()->json([
                'data' => [
                    'id' => $reservation->id,
                    'restaurant_id' => $reservation->restaurant_id,
                    'customer_name' => $reservation->customer_name,
                    'customer_email' => $reservation->customer_email,
                    'party_size' => $reservation->party_size,
                    'reservation_date' => $reservation->reservation_date->toDateString(),
                    'reservation_time' => $reservation->reservation_time,
                    'status' => $reservation->status,
                    'table_id' => $reservation->table_id,
                ],
            ], 201);
        }

        $reservation = $engine->addWaitlist(
            $restaurant->id,
            (int) $request->input('party_size'),
            $request->input('reservation_date'),
            $request->input('reservation_time'),
            $request->input('customer_name'),
            $request->input('customer_email'),
            $request->input('customer_phone')
        );

        return response()->json([
            'data' => [
                'id' => $reservation->id,
                'restaurant_id' => $reservation->restaurant_id,
                'customer_name' => $reservation->customer_name,
                'customer_email' => $reservation->customer_email,
                'party_size' => $reservation->party_size,
                'reservation_date' => $reservation->reservation_date->toDateString(),
                'reservation_time' => $reservation->reservation_time,
                'status' => $reservation->status,
                'on_waitlist' => true,
            ],
        ], 201);
    }

    public function show(Reservation $reservation): JsonResponse
    {
        $user = request()->user();

        if ($user !== null && $reservation->restaurant_id !== $user->restaurant_id) {
            return response()->json(['message' => 'Reservation not found.'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $reservation->id,
                'restaurant_id' => $reservation->restaurant_id,
                'customer_name' => $reservation->customer_name,
                'customer_email' => $reservation->customer_email,
                'customer_phone' => $reservation->customer_phone,
                'party_size' => $reservation->party_size,
                'reservation_date' => $reservation->reservation_date->toDateString(),
                'reservation_time' => $reservation->reservation_time,
                'status' => $reservation->status,
                'table_id' => $reservation->table_id,
                'notes' => $reservation->notes,
                'created_at' => $reservation->created_at,
                'updated_at' => $reservation->updated_at,
            ],
        ], 200);
    }
}
