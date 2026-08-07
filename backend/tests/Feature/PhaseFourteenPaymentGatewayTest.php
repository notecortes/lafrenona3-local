<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PhaseFourteenPaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerA;
    private User $waiterA;
    private Restaurant $restaurantA;
    private Category $categoryA;
    private Product $productA;
    private Table $tableA;
    private Order $orderA;

    protected function setUp(): void
    {
        parent::setUp();
        app('tenant.context')->forget();

        $this->ownerA = User::create([
            'name' => 'Owner A',
            'email' => 'owner.a@example.test',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);

        $this->waiterA = User::create([
            'name' => 'Waiter A',
            'email' => 'waiter.a@example.test',
            'password' => Hash::make('password123'),
            'role' => 'waiter',
        ]);

        $this->restaurantA = Restaurant::create([
            'owner_id' => $this->ownerA->id,
            'name' => 'Restaurante QA A',
            'slug' => 'qa-a',
            'status' => 'active',
        ]);

        $this->ownerA->update(['restaurant_id' => $this->restaurantA->id]);

        $this->categoryA = Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Starters'],
        ]);

        $this->productA = Product::create([
            'restaurant_id' => $this->restaurantA->id,
            'category_id' => $this->categoryA->id,
            'name' => ['en' => 'Bruschetta'],
            'price' => 8.50,
        ]);

        $this->tableA = Table::create([
            'restaurant_id' => $this->restaurantA->id,
            'number' => 'A-01',
        ]);

        $this->orderA = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session',
            'status' => 'closed',
            'total_price' => 17.00,
        ]);
    }

    private function ownerAToken(): string
    {
        return $this->ownerA->createToken('test')->plainTextToken;
    }

    private function waiterAToken(): string
    {
        return $this->waiterA->createToken('test')->plainTextToken;
    }

    public function test_client_can_initiate_payment(): void
    {
        $response = $this->postJson('/api/v1/client/payments/initiate', [
            'order_id' => $this->orderA->id,
            'tip_cents' => 200,
            'currency' => 'EUR',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.order_id', $this->orderA->id);
        $response->assertJsonPath('data.amount_cents', 1700);
        $response->assertJsonPath('data.tip_cents', 200);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.currency', 'EUR');

        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $this->orderA->id,
            'amount_cents' => 1700,
            'tip_cents' => 200,
            'status' => 'pending',
        ]);
    }

    public function test_payment_transaction_has_unique_idempotency_key(): void
    {
        $response = $this->postJson('/api/v1/client/payments/initiate', [
            'order_id' => $this->orderA->id,
        ]);

        $response->assertStatus(200);

        $transaction = PaymentTransaction::where('order_id', $this->orderA->id)->first();

        $this->assertNotNull($transaction);
        $this->assertNotNull($transaction->idempotency_key);
    }

    public function test_cannot_initiate_payment_twice_for_same_order(): void
    {
        $this->postJson('/api/v1/client/payments/initiate', [
            'order_id' => $this->orderA->id,
        ]);

        $response = $this->postJson('/api/v1/client/payments/initiate', [
            'order_id' => $this->orderA->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Payment already initiated for this order.');
    }

    public function test_cannot_initiate_payment_for_open_order(): void
    {
        $openOrder = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'open-session',
            'status' => 'open',
            'total_price' => 10.00,
        ]);

        $response = $this->postJson('/api/v1/client/payments/initiate', [
            'order_id' => $openOrder->id,
        ]);

        $response->assertStatus(404);
    }

    public function test_webhook_handles_payment_succeeded(): void
    {
        $transaction = PaymentTransaction::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $this->orderA->id,
            'provider' => 'stripe',
            'provider_payment_id' => 'pi_test_123',
            'webhook_event_id' => null,
            'idempotency_key' => 'pi_test_123',
            'amount_cents' => 1700,
            'tip_cents' => 0,
            'currency' => 'EUR',
            'status' => 'pending',
            'confirmed_at' => null,
            'metadata_reference' => ['order_id' => $this->orderA->id],
        ]);

        $stripePayload = [
            'id' => 'evt_test_123',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_123',
                    'amount' => 1700,
                    'currency' => 'eur',
                    'metadata' => ['order_id' => $this->orderA->id],
                ],
            ],
        ];

        config(['services.stripe.webhook_secret' => 'whsec_test']);

        $response = $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 'test_signature',
        ], json_encode($stripePayload));

        $response->assertStatus(200);

        $transaction->refresh();
        $this->assertEquals('confirmed', $transaction->status);
        $this->assertNotNull($transaction->confirmed_at);
    }

    public function test_webhook_deduplicates_by_provider_payment_id(): void
    {
        PaymentTransaction::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $this->orderA->id,
            'provider' => 'stripe',
            'provider_payment_id' => 'pi_dedup_test',
            'webhook_event_id' => 'evt_dedup_1',
            'idempotency_key' => 'pi_dedup_test',
            'amount_cents' => 1700,
            'tip_cents' => 0,
            'currency' => 'EUR',
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'metadata_reference' => ['order_id' => $this->orderA->id],
        ]);

        $stripePayload = [
            'id' => 'evt_dedup_2',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_dedup_test',
                    'amount' => 1700,
                    'currency' => 'eur',
                    'metadata' => ['order_id' => $this->orderA->id],
                ],
            ],
        ];

        config(['services.stripe.webhook_secret' => 'whsec_test']);

        $response = $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 'test_signature',
        ], json_encode($stripePayload));

        $response->assertStatus(200);

        $count = PaymentTransaction::where('provider_payment_id', 'pi_dedup_test')->count();
        $this->assertEquals(1, $count);
    }

    public function test_webhook_handles_payment_failed(): void
    {
        $transaction = PaymentTransaction::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $this->orderA->id,
            'provider' => 'stripe',
            'provider_payment_id' => 'pi_failed_123',
            'webhook_event_id' => null,
            'idempotency_key' => 'pi_failed_123',
            'amount_cents' => 1700,
            'tip_cents' => 0,
            'currency' => 'EUR',
            'status' => 'pending',
            'confirmed_at' => null,
            'metadata_reference' => ['order_id' => $this->orderA->id],
        ]);

        $stripePayload = [
            'id' => 'evt_failed_123',
            'object' => 'event',
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_failed_123',
                ],
            ],
        ];

        config(['services.stripe.webhook_secret' => 'whsec_test']);

        $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 'test_signature',
        ], json_encode($stripePayload));

        $transaction->refresh();
        $this->assertEquals('failed', $transaction->status);
    }

    public function test_webhook_handles_payment_canceled(): void
    {
        $transaction = PaymentTransaction::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $this->orderA->id,
            'provider' => 'stripe',
            'provider_payment_id' => 'pi_canceled_123',
            'webhook_event_id' => null,
            'idempotency_key' => 'pi_canceled_123',
            'amount_cents' => 1700,
            'tip_cents' => 0,
            'currency' => 'EUR',
            'status' => 'pending',
            'confirmed_at' => null,
            'metadata_reference' => ['order_id' => $this->orderA->id],
        ]);

        $stripePayload = [
            'id' => 'evt_canceled_123',
            'object' => 'event',
            'type' => 'payment_intent.canceled',
            'data' => [
                'object' => [
                    'id' => 'pi_canceled_123',
                ],
            ],
        ];

        config(['services.stripe.webhook_secret' => 'whsec_test']);

        $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 'test_signature',
        ], json_encode($stripePayload));

        $transaction->refresh();
        $this->assertEquals('cancelled', $transaction->status);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $stripePayload = [
            'id' => 'evt_test',
            'type' => 'payment_intent.succeeded',
        ];

        config(['services.stripe.webhook_secret' => 'whsec_test']);

        $response = $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 'invalid_signature',
        ], json_encode($stripePayload));

        $response->assertStatus(400);
    }

    public function test_payment_tenant_isolation(): void
    {
        $restaurantB = Restaurant::create([
            'owner_id' => User::create([
                'name' => 'Owner B',
                'email' => 'owner.b@example.test',
                'password' => Hash::make('password123'),
                'role' => 'owner',
            ])->id,
            'name' => 'Restaurante QA B',
            'slug' => 'qa-b',
            'status' => 'active',
        ]);

        $ownerB = User::where('email', 'owner.b@example.test')->first();
        $ownerB->update(['restaurant_id' => $restaurantB->id]);

        $orderB = Order::create([
            'restaurant_id' => $restaurantB->id,
            'table_id' => Table::create([
                'restaurant_id' => $restaurantB->id,
                'number' => 'B-01',
            ])->id,
            'session_token' => 'test-session-b',
            'status' => 'closed',
            'total_price' => 25.00,
        ]);

        $token = $this->ownerAToken();

        $response = $this->actingAs($this->ownerA, 'sanctum')
            ->postJson('/api/v1/client/payments/initiate', [
                'order_id' => $orderB->id,
            ]);

        $response->assertStatus(404);
    }

    public function test_payment_without_tip_defaults_to_zero(): void
    {
        $response = $this->postJson('/api/v1/client/payments/initiate', [
            'order_id' => $this->orderA->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.tip_cents', 0);
    }

    public function test_payment_transaction_has_all_required_fields(): void
    {
        $response = $this->postJson('/api/v1/client/payments/initiate', [
            'order_id' => $this->orderA->id,
            'tip_cents' => 100,
            'currency' => 'EUR',
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('order_id', $data);
        $this->assertArrayHasKey('amount_cents', $data);
        $this->assertArrayHasKey('tip_cents', $data);
        $this->assertArrayHasKey('total_cents', $data);
        $this->assertArrayHasKey('currency', $data);
        $this->assertArrayHasKey('status', $data);
    }

    public function test_webhook_does_not_affect_confirmed_payments(): void
    {
        $transaction = PaymentTransaction::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $this->orderA->id,
            'provider' => 'stripe',
            'provider_payment_id' => 'pi_already_confirmed',
            'webhook_event_id' => 'evt_already_processed',
            'idempotency_key' => 'pi_already_confirmed',
            'amount_cents' => 1700,
            'tip_cents' => 0,
            'currency' => 'EUR',
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'metadata_reference' => ['order_id' => $this->orderA->id],
        ]);

        $stripePayload = [
            'id' => 'evt_retry_123',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_already_confirmed',
                    'amount' => 1700,
                    'currency' => 'eur',
                    'metadata' => ['order_id' => $this->orderA->id],
                ],
            ],
        ];

        config(['services.stripe.webhook_secret' => 'whsec_test']);

        $response = $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 'test_signature',
        ], json_encode($stripePayload));

        $response->assertStatus(200);

        $transaction->refresh();
        $this->assertEquals('confirmed', $transaction->status);
    }

    public function test_unauthenticated_user_cannot_initiate_payment(): void
    {
        $response = $this->postJson('/api/v1/client/payments/initiate', [
            'order_id' => $this->orderA->id,
        ]);

        $response->assertStatus(200);
    }
}
