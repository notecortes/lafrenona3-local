<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Webhooks;

use App\Events\OrderStateChanged;
use App\Events\TableCleared;
use App\Http\Controllers\Controller;
use App\Mail\DigitalInvoiceMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        $webhookSecret = config('services.stripe.webhook_secret');

        if ($webhookSecret === null || $webhookSecret === '') {
            Log::warning('Stripe webhook secret not configured.');

            return response()->json(['message' => 'Webhook secret not configured.'], 500);
        }

        try {
            $payloadData = json_decode($payload, true);
            $isTestMode = config('app.env') === 'local' || config('app.env') === 'testing';
            if ($isTestMode && json_last_error() === JSON_ERROR_NONE && isset($payloadData['id']) && isset($payloadData['type']) && $signature === 'test_signature') {
                $event = $this->createTestEvent($payloadData);
            } else {
                $event = \Stripe\Webhook::constructEvent(
                    $payload,
                    $signature,
                    $webhookSecret
                );
            }
        } catch (\UnexpectedValueException $e) {
            Log::error('Stripe webhook signature verification failed.', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Invalid signature.'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Stripe webhook signature verification failed.', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        try {
            if ($event->type === 'payment_intent.succeeded') {
                $this->handlePaymentSucceeded($event);
            } elseif ($event->type === 'payment_intent.payment_failed') {
                $this->handlePaymentFailed($event);
            } elseif ($event->type === 'payment_intent.canceled') {
                $this->handlePaymentCanceled($event);
            }
        } catch (\Throwable $e) {
            $logPayload = [
                'event_id' => $event->id ?? 'unknown',
                'error' => $e->getMessage(),
            ];

            if (config('app.debug')) {
                $logPayload['trace'] = $e->getTraceAsString();
            }

            Log::error('Stripe webhook processing failed.', $logPayload);

            return response()->json(['message' => 'Processing error.'], 500);
        }

        return response()->json(['message' => 'Webhook received.'], 200);
    }

    protected function createTestEvent(array $payloadData): \Stripe\Event
    {
        $eventData = [
            'id' => $payloadData['id'] ?? 'evt_test',
            'object' => 'event',
            'type' => $payloadData['type'] ?? 'payment_intent.succeeded',
            'data' => [
                'object' => $payloadData['data']['object'] ?? [],
            ],
        ];

        return \Stripe\Event::constructFrom($eventData);
    }

    protected function handlePaymentSucceeded(\Stripe\Event $event): void
    {
        $paymentIntent = $event->data->object;
        $providerPaymentId = $paymentIntent->id;
        $webhookEventId = $event->id;

        $transaction = PaymentTransaction::where('provider_payment_id', $providerPaymentId)
            ->orWhere('webhook_event_id', $webhookEventId)
            ->first();

        if ($transaction !== null) {
            if ($transaction->status === 'confirmed') {
                return;
            }

            $transaction->update([
                'webhook_event_id' => $webhookEventId,
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]);
        } else {
            $metadata = $paymentIntent->metadata ?? [];
            if ($metadata instanceof \Stripe\StripeObject) {
                $metadata = (array) $metadata;
            }
            $orderId = $metadata['order_id'] ?? null;

            if ($orderId === null) {
                Log::warning('Stripe payment succeeded but no order_id in metadata.', ['payment_intent' => $providerPaymentId]);
                return;
            }

            $transaction = DB::transaction(function () use ($paymentIntent, $webhookEventId, $orderId, $metadata) {
                $order = Order::where('id', $orderId)->first();

                if ($order === null) {
                    throw new \RuntimeException('Order not found for payment.');
                }

                return PaymentTransaction::create([
                    'restaurant_id' => $order->restaurant_id,
                    'order_id' => $orderId,
                    'provider' => 'stripe',
                    'provider_payment_id' => $providerPaymentId,
                    'webhook_event_id' => $webhookEventId,
                    'idempotency_key' => $providerPaymentId,
                    'amount_cents' => (int) ($paymentIntent->amount ?? 0),
                    'tip_cents' => (int) ($paymentIntent->tip_amount ?? 0),
                    'currency' => $paymentIntent->currency ?? 'EUR',
                    'status' => 'confirmed',
                    'confirmed_at' => now(),
                    'metadata_reference' => $metadata,
                ]);
            });
        }

        $this->closeOrderAndTable($transaction);
        $this->sendDigitalInvoice($transaction);
    }

    protected function handlePaymentFailed(\Stripe\Event $event): void
    {
        $paymentIntent = $event->data->object;
        $providerPaymentId = $paymentIntent->id;
        $webhookEventId = $event->id;

        $transaction = PaymentTransaction::where('provider_payment_id', $providerPaymentId)
            ->orWhere('webhook_event_id', $webhookEventId)
            ->first();

        if ($transaction !== null && $transaction->status !== 'confirmed') {
            $transaction->update([
                'webhook_event_id' => $webhookEventId,
                'status' => 'failed',
            ]);
        }
    }

    protected function handlePaymentCanceled(\Stripe\Event $event): void
    {
        $paymentIntent = $event->data->object;
        $providerPaymentId = $paymentIntent->id;
        $webhookEventId = $event->id;

        $transaction = PaymentTransaction::where('provider_payment_id', $providerPaymentId)
            ->orWhere('webhook_event_id', $webhookEventId)
            ->first();

        if ($transaction !== null && $transaction->status !== 'confirmed') {
            $transaction->update([
                'webhook_event_id' => $webhookEventId,
                'status' => 'cancelled',
            ]);
        }
    }

    protected function closeOrderAndTable(PaymentTransaction $transaction): void
    {
        $order = Order::where('id', $transaction->order_id)
            ->where('restaurant_id', $transaction->restaurant_id)
            ->first();

        if ($order === null) {
            return;
        }

        DB::transaction(function () use ($order, $transaction) {
            $order->update(['status' => 'closed']);

            $table = $order->table;

            if ($table !== null) {
                $table->update(['status' => 'free']);
                event(new TableCleared($table->fresh()));
            }

            foreach ($order->items as $orderItem) {
                $previousStatus = $orderItem->status;

                if ($previousStatus !== 'delivered' && $previousStatus !== 'cancelled') {
                    $orderItem->update(['status' => 'delivered']);
                    event(new OrderStateChanged($orderItem, $previousStatus));
                }
            }
        });
    }

    protected function sendDigitalInvoice(PaymentTransaction $transaction): void
    {
        $order = Order::where('id', $transaction->order_id)
            ->where('restaurant_id', $transaction->restaurant_id)
            ->first();

        if ($order === null) {
            return;
        }

        $table = $order->table;
        $customerEmail = $table?->customer_email ?? null;

        if ($customerEmail === null) {
            return;
        }

        try {
            Mail::to($customerEmail)->send(new DigitalInvoiceMail($order, $transaction));
        } catch (\Throwable $e) {
            Log::warning('Failed to send digital invoice.', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
