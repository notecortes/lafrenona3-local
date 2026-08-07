<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FiscalRecord;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class FiscalChainingService
{
    public function generateHash(array $data): string
    {
        $canonical = $this->toCanonicalJson($data);

        return hash('sha256', $canonical);
    }

    public function createFiscalRecord(Order $order): FiscalRecord
    {
        return DB::transaction(function () use ($order) {
            $lastRecord = FiscalRecord::where('restaurant_id', $order->restaurant_id)
                ->orderBy('sequence_number', 'desc')
                ->first();

            $sequenceNumber = $lastRecord !== null
                ? $lastRecord->sequence_number + 1
                : 1;

            $prevHash = $lastRecord !== null
                ? $lastRecord->hash
                : hash('sha256', 'genesis');

            $dataToHash = [
                'sequence_number' => $sequenceNumber,
                'order_id' => $order->id,
                'total_amount' => $order->total_price,
                'restaurant_id' => $order->restaurant_id,
                'prev_hash' => $prevHash,
            ];

            $hash = $this->generateHash($dataToHash);

            $taxAmount = round((float) $order->total_price * 0.10, 2);

            $record = FiscalRecord::create([
                'restaurant_id' => $order->restaurant_id,
                'order_id' => $order->id,
                'sequence_number' => $sequenceNumber,
                'hash' => $hash,
                'prev_hash' => $prevHash,
                'total_amount' => $order->total_price,
                'tax_amount' => $taxAmount,
                'currency' => 'EUR',
                'status' => 'issued',
            ]);

            return $record;
        });
    }

    public function verifyChain(int $restaurantId): bool
    {
        $records = FiscalRecord::where('restaurant_id', $restaurantId)
            ->orderBy('sequence_number', 'asc')
            ->get();

        if ($records->isEmpty()) {
            return true;
        }

        $expectedPrevHash = hash('sha256', 'genesis');

        foreach ($records as $record) {
            if ($record->prev_hash !== $expectedPrevHash) {
                return false;
            }

            $dataToVerify = [
                'sequence_number' => $record->sequence_number,
                'order_id' => $record->order_id,
                'total_amount' => $record->total_amount,
                'restaurant_id' => $record->restaurant_id,
                'prev_hash' => $record->prev_hash,
            ];

            $expectedHash = $this->generateHash($dataToVerify);

            if ($record->hash !== $expectedHash) {
                return false;
            }

            $expectedPrevHash = $record->hash;
        }

        return true;
    }

    protected function toCanonicalJson(array $data): string
    {
        $filtered = [];

        foreach ($data as $key => $value) {
            if ($value !== null) {
                $filtered[$key] = $value;
            }
        }

        ksort($filtered);

        return json_encode($filtered, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
