<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClientPaymentController extends Controller
{
    public function initiate(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'order_id' => 'required|integer',
            'tip_cents' => 'nullable|integer|min:0',
            'currency' => 'nullable|string|in:EUR,USD,GBP',
        ]);

        $order = Order::where('id', $request->input('order_id'))
            ->where('status', 'closed')
            ->when($user !== null && $user->restaurant_id !== null, function ($q) use ($user) {
                return $q->where('restaurant_id', $user->restaurant_id);
            })
            ->first();

        if ($order === null) {
            return response()->json(['message' => 'Order not found or not ready for payment.'], 404);
        }

        $hasPayment = \App\Models\PaymentTransaction::where('order_id', $order->id)
            ->whereIn('status', ['confirmed', 'pending'])
            ->exists();

        if ($hasPayment) {
            return response()->json(['message' => 'Payment already initiated for this order.'], 422);
        }

        $idempotencyKey = (string) Str::uuid();
        $amountCents = (int) round((float) $order->total_price * 100);
        $tipCents = (int) ($request->input('tip_cents') ?? 0);
        $currency = $request->input('currency', 'EUR');

        $transaction = DB::transaction(function () use ($order, $idempotencyKey, $amountCents, $tipCents, $currency) {
            return \App\Models\PaymentTransaction::create([
                'restaurant_id' => $order->restaurant_id,
                'order_id' => $order->id,
                'provider' => 'stripe',
                'provider_payment_id' => null,
                'webhook_event_id' => null,
                'idempotency_key' => $idempotencyKey,
                'amount_cents' => $amountCents,
                'tip_cents' => $tipCents,
                'currency' => $currency,
                'status' => 'pending',
                'confirmed_at' => null,
                'metadata_reference' => null,
            ]);
        });

        return response()->json([
            'data' => [
                'id' => $transaction->id,
                'order_id' => $transaction->order_id,
                'amount_cents' => $transaction->amount_cents,
                'tip_cents' => $transaction->tip_cents,
                'total_cents' => $transaction->amount_cents + $transaction->tip_cents,
                'currency' => $transaction->currency,
                'status' => $transaction->status,
                'provider_payment_id' => 'pi_simulated_' . Str::random(24),
                'client_secret' => 'pi_simulated_' . Str::random(24) . '_secret_' . Str::random(24),
            ],
        ], 200);
    }
}
