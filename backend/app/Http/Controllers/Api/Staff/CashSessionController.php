<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\CashSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashSessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $sessions = CashSession::where('restaurant_id', $restaurantId)
            ->with(['user'])
            ->orderBy('opened_at', 'desc')
            ->get();

        return response()->json([
            'data' => $sessions->map(fn ($session) => [
                'id' => $session->id,
                'restaurant_id' => $session->restaurant_id,
                'user_id' => $session->user_id,
                'user_name' => $session->user?->name,
                'opened_at' => $session->opened_at?->toIso8601String(),
                'closed_at' => $session->closed_at?->toIso8601String(),
                'opening_amount' => $session->opening_amount,
                'expected_amount' => $session->expected_amount,
                'actual_amount' => $session->actual_amount,
                'status' => $session->status,
            ]),
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $restaurantId = $user->restaurant_id;

        $request->validate([
            'opening_amount' => 'required|numeric|min:0',
        ]);

        $openSession = CashSession::where('restaurant_id', $restaurantId)
            ->where('status', 'open')
            ->first();

        if ($openSession !== null) {
            return response()->json(['message' => 'An open cash session already exists.'], 422);
        }

        $session = CashSession::create([
            'restaurant_id' => $restaurantId,
            'user_id' => $user->id,
            'opened_at' => now(),
            'opening_amount' => (float) $request->input('opening_amount'),
            'expected_amount' => 0,
            'actual_amount' => null,
            'status' => 'open',
        ]);

        return response()->json([
            'data' => [
                'id' => $session->id,
                'restaurant_id' => $session->restaurant_id,
                'user_id' => $session->user_id,
                'opened_at' => $session->opened_at?->toIso8601String(),
                'opening_amount' => $session->opening_amount,
                'expected_amount' => $session->expected_amount,
                'status' => $session->status,
            ],
        ], 201);
    }

    public function close(Request $request, CashSession $cashSession): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->restaurant_id !== $cashSession->restaurant_id) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        if ($cashSession->status === 'closed') {
            return response()->json(['message' => 'Session is already closed.'], 422);
        }

        $request->validate([
            'actual_amount' => 'required|numeric|min:0',
        ]);

        $cashSession->update([
            'closed_at' => now(),
            'expected_amount' => $cashSession->opening_amount,
            'actual_amount' => (float) $request->input('actual_amount'),
            'status' => 'closed',
        ]);

        return response()->json([
            'data' => [
                'id' => $cashSession->id,
                'status' => $cashSession->status,
                'opening_amount' => $cashSession->opening_amount,
                'expected_amount' => $cashSession->expected_amount,
                'actual_amount' => $cashSession->actual_amount,
                'difference' => number_format((float) $cashSession->actual_amount - (float) $cashSession->expected_amount, 2, '.', ''),
                'closed_at' => $cashSession->closed_at?->toIso8601String(),
            ],
        ], 200);
    }
}
