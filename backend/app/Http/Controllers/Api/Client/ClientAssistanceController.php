<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client;

use App\Events\ClientAssistanceRequested;
use App\Http\Controllers\Controller;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientAssistanceController extends Controller
{
    public function request(Request $request): JsonResponse
    {
        $request->validate([
            'session_token' => 'required|string|max:64',
        ]);

        $table = Table::where('secret_token', $request->input('session_token'))
            ->first();

        if ($table === null) {
            return response()->json(['message' => 'Invalid session token.'], 404);
        }

        $assistanceType = $request->input('type', 'waiter_called');

        if (! in_array($assistanceType, ['waiter_called', 'bill_requested'], true)) {
            return response()->json(['message' => 'Invalid assistance type.'], 422);
        }

        DB::transaction(function () use ($table, $assistanceType) {
            $table->update([
                'assistance_status' => $assistanceType,
                'assistance_requested_at' => now(),
            ]);
        });

        event(new ClientAssistanceRequested($table->fresh(), $assistanceType));

        return response()->json([
            'data' => [
                'table_id' => $table->id,
                'table_number' => $table->number,
                'assistance_status' => $assistanceType,
                'assistance_requested_at' => $table->assistance_requested_at?->toIso8601String(),
            ],
        ], 200);
    }
}
