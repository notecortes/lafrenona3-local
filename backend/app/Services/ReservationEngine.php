<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Support\Facades\DB;

class ReservationEngine
{
    public function findAvailableTable(
        int $restaurantId,
        int $partySize,
        string $date,
        string $time
    ): ?Table {
        $exclusionMinutes = 60;

        return DB::transaction(function () use ($restaurantId, $partySize, $date, $time, $exclusionMinutes) {
            $tables = Table::lockForUpdate()
                ->where('restaurant_id', $restaurantId)
                ->where('status', 'free')
                ->where('capacity', '>=', $partySize)
                ->get();

            foreach ($tables as $table) {
                $hasConflict = Reservation::where('restaurant_id', $restaurantId)
                    ->where('table_id', $table->id)
                    ->whereNotIn('status', ['completed', 'cancelled'])
                    ->where('reservation_date', $date)
                    ->whereRaw(
                        'ABS((julianday(?) - julianday(reservation_time)) * 1440) <= ?',
                        [$date . ' ' . $time, $exclusionMinutes]
                    )
                    ->exists();

                if (! $hasConflict) {
                    return $table;
                }
            }

            return null;
        });
    }

    public function addWaitlist(
        int $restaurantId,
        int $partySize,
        string $date,
        string $time,
        string $name,
        string $email,
        string $phone
    ): Reservation {
        return Reservation::create([
            'restaurant_id' => $restaurantId,
            'customer_name' => $name,
            'customer_email' => $email,
            'customer_phone' => $phone,
            'party_size' => $partySize,
            'reservation_date' => $date,
            'reservation_time' => $time,
            'status' => 'pending',
            'table_id' => null,
            'notes' => 'Waitlist entry',
        ]);
    }

    public function seatReservation(int $reservationId, int $tableId): Reservation
    {
        return DB::transaction(function () use ($reservationId, $tableId) {
            $reservation = Reservation::where('id', $reservationId)
                ->where('status', 'confirmed')
                ->lockForUpdate()
                ->first();

            if ($reservation === null) {
                throw new \RuntimeException('Reservation not found or not confirmed.');
            }

            $table = Table::where('id', $tableId)
                ->where('status', 'free')
                ->lockForUpdate()
                ->first();

            if ($table === null) {
                throw new \RuntimeException('Table not found or not free.');
            }

            $table->update(['status' => 'occupied']);

            $reservation->update([
                'status' => 'seated',
                'table_id' => $tableId,
            ]);

            return $reservation->fresh();
        });
    }
}
