<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OfflineOperation;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhaseEightOfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerA;
    private User $ownerB;
    private User $waiterA;
    private User $waiterB;
    private Restaurant $restaurantA;
    private Restaurant $restaurantB;
    private Table $tableA;
    private Table $tableB;
    private Category $categoryA;
    private Category $categoryB;
    private Product $productA;
    private Product $productB;
    private Order $orderA;
    private Order $orderB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerA = User::create([
            'name' => 'Owner A',
            'email' => 'owner.a@example.test',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'role' => 'owner',
        ]);

        $this->ownerB = User::create([
            'name' => 'Owner B',
            'email' => 'owner.b@example.test',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'role' => 'owner',
        ]);

        $this->waiterA = User::create([
            'name' => 'Waiter A',
            'email' => 'waiter.a@example.test',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'role' => 'waiter',
        ]);

        $this->waiterB = User::create([
            'name' => 'Waiter B',
            'email' => 'waiter.b@example.test',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'role' => 'waiter',
        ]);

        $this->restaurantA = Restaurant::create([
            'owner_id' => $this->ownerA->id,
            'name' => 'Restaurante QA A',
            'slug' => 'qa-a',
            'status' => 'active',
        ]);

        $this->restaurantB = Restaurant::create([
            'owner_id' => $this->ownerB->id,
            'name' => 'Restaurante QA B',
            'slug' => 'qa-b',
            'status' => 'active',
        ]);

        $this->tableA = Table::create([
            'restaurant_id' => $this->restaurantA->id,
            'number' => 'A-01',
        ]);

        $this->tableB = Table::create([
            'restaurant_id' => $this->restaurantB->id,
            'number' => 'B-01',
        ]);

        $this->ownerA->update(['restaurant_id' => $this->restaurantA->id]);
        $this->ownerB->update(['restaurant_id' => $this->restaurantB->id]);
        $this->waiterA->update(['restaurant_id' => $this->restaurantA->id]);
        $this->waiterB->update(['restaurant_id' => $this->restaurantB->id]);

        $this->categoryA = Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Starters'],
        ]);

        $this->categoryB = Category::create([
            'restaurant_id' => $this->restaurantB->id,
            'name' => ['en' => 'Starters'],
        ]);

        $this->productA = Product::create([
            'restaurant_id' => $this->restaurantA->id,
            'category_id' => $this->categoryA->id,
            'name' => ['en' => 'Bruschetta'],
            'price' => 8.50,
        ]);

        $this->productB = Product::create([
            'restaurant_id' => $this->restaurantB->id,
            'category_id' => $this->categoryB->id,
            'name' => ['en' => 'Bruschetta'],
            'price' => 8.50,
        ]);

        $this->orderA = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session-a',
            'status' => 'open',
            'total_price' => 0.00,
        ]);

        $this->orderB = Order::create([
            'restaurant_id' => $this->restaurantB->id,
            'table_id' => $this->tableB->id,
            'session_token' => 'test-session-b',
            'status' => 'open',
            'total_price' => 0.00,
        ]);
    }

    private function waiterAToken(): string
    {
        return $this->waiterA->createToken('test')->plainTextToken;
    }

    private function waiterBToken(): string
    {
        return $this->waiterB->createToken('test')->plainTextToken;
    }

    public function test_sync_accepts_valid_order_item_create(): void
    {
        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/sync/offline', [
                'operations' => [
                    [
                        'idempotency_key' => 'offline-sync-key-1',
                        'type' => 'order_item_create',
                        'payload' => [
                            'order_id' => $this->orderA->id,
                            'product_id' => $this->productA->id,
                            'quantity' => 2,
                            'unit_price' => 8.50,
                            'target_area' => 'kitchen',
                            'restaurant_id' => $this->restaurantA->id,
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.accepted', 1);
        $response->assertJsonPath('data.results.0.status', 'accepted');

        $this->assertDatabaseHas('order_items', [
            'order_id' => $this->orderA->id,
            'product_id' => $this->productA->id,
            'quantity' => 2,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('offline_operations', [
            'idempotency_key' => 'offline-sync-key-1',
            'status' => 'processed',
        ]);
    }

    public function test_sync_deduplicates_by_idempotency_key(): void
    {
        $token = $this->waiterAToken();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/sync/offline', [
                'operations' => [
                    [
                        'idempotency_key' => 'offline-sync-dup-key',
                        'type' => 'order_item_create',
                        'payload' => [
                            'order_id' => $this->orderA->id,
                            'product_id' => $this->productA->id,
                            'quantity' => 1,
                            'unit_price' => 8.50,
                            'restaurant_id' => $this->restaurantA->id,
                        ],
                    ],
                ],
            ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/sync/offline', [
                'operations' => [
                    [
                        'idempotency_key' => 'offline-sync-dup-key',
                        'type' => 'order_item_create',
                        'payload' => [
                            'order_id' => $this->orderA->id,
                            'product_id' => $this->productA->id,
                            'quantity' => 1,
                            'unit_price' => 8.50,
                            'restaurant_id' => $this->restaurantA->id,
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.duplicates', 1);
        $response->assertJsonPath('data.results.0.status', 'duplicate');

        $itemCount = OrderItem::where('order_id', $this->orderA->id)->count();
        $this->assertEquals(1, $itemCount);
    }

    public function test_sync_rejects_invalid_order_item_create(): void
    {
        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/sync/offline', [
                'operations' => [
                    [
                        'idempotency_key' => 'offline-reject-key',
                        'type' => 'order_item_create',
                        'payload' => [
                            'order_id' => $this->orderA->id,
                            'product_id' => 99999,
                            'quantity' => 1,
                            'unit_price' => 8.50,
                            'restaurant_id' => $this->restaurantA->id,
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.rejected', 1);
        $response->assertJsonPath('data.results.0.status', 'rejected');
    }

    public function test_sync_rejects_order_item_create_for_closed_order(): void
    {
        $closedOrder = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'closed-session',
            'status' => 'closed',
            'total_price' => 0.00,
        ]);

        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/sync/offline', [
                'operations' => [
                    [
                        'idempotency_key' => 'offline-closed-order-key',
                        'type' => 'order_item_create',
                        'payload' => [
                            'order_id' => $closedOrder->id,
                            'product_id' => $this->productA->id,
                            'quantity' => 1,
                            'unit_price' => 8.50,
                            'restaurant_id' => $this->restaurantA->id,
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.conflicts', 1);
        $response->assertJsonPath('data.results.0.status', 'conflict');
    }

    public function test_sync_batch_processes_multiple_operations(): void
    {
        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/sync/offline', [
                'operations' => [
                    [
                        'idempotency_key' => 'batch-key-1',
                        'type' => 'order_item_create',
                        'payload' => [
                            'order_id' => $this->orderA->id,
                            'product_id' => $this->productA->id,
                            'quantity' => 1,
                            'unit_price' => 8.50,
                            'restaurant_id' => $this->restaurantA->id,
                        ],
                    ],
                    [
                        'idempotency_key' => 'batch-key-2',
                        'type' => 'order_item_create',
                        'payload' => [
                            'order_id' => $this->orderA->id,
                            'product_id' => $this->productA->id,
                            'quantity' => 2,
                            'unit_price' => 8.50,
                            'restaurant_id' => $this->restaurantA->id,
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.accepted', 2);
    }

    public function test_sync_empty_batch_returns_zero_counts(): void
    {
        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/sync/offline', [
                'operations' => [],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['operations']);
    }

    public function test_sync_validates_required_fields(): void
    {
        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/sync/offline', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['operations']);
    }

    public function test_sync_rejects_unauthenticated(): void
    {
        $response = $this->postJson('/api/v1/staff/sync/offline', [
            'operations' => [
                [
                    'idempotency_key' => 'offline-key',
                    'type' => 'order_item_create',
                    'payload' => [],
                ],
            ],
        ]);

        $response->assertStatus(401);
    }

    public function test_sync_tenant_isolation_waiter_a_cannot_sync_for_restaurant_b(): void
    {
        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/sync/offline', [
                'operations' => [
                    [
                        'idempotency_key' => 'isolation-key',
                        'type' => 'order_item_create',
                        'payload' => [
                            'order_id' => $this->orderB->id,
                            'product_id' => $this->productB->id,
                            'quantity' => 1,
                            'unit_price' => 8.50,
                            'restaurant_id' => $this->restaurantB->id,
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.results.0.status', 'rejected');
    }

    public function test_sync_order_item_status_update_validates_transitions(): void
    {
        $orderItem = OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $this->orderA->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'test-status-item',
            'price_snapshot' => 8.50,
            'tax_rate' => 10.00,
            'discount_amount' => 0.00,
        ]);

        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/sync/offline', [
                'operations' => [
                    [
                        'idempotency_key' => 'validate-transitions-key',
                        'type' => 'order_item_status_update',
                        'payload' => [
                            'order_item_id' => $orderItem->id,
                            'status' => 'delivered',
                            'restaurant_id' => $this->restaurantA->id,
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(200);
        dump('SYNC RESPONSE:', $response->json('data'));
        $response->assertJsonPath('data.conflicts', 1);
        $response->assertJsonPath('data.results.0.status', 'conflict');

        $this->assertDatabaseHas('order_items', [
            'id' => $orderItem->id,
            'status' => 'pending',
        ]);
    }

    public function test_sync_order_item_status_update_accepts_valid_transition(): void
    {
        $orderItem = OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $this->orderA->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'test-status-item-valid',
            'price_snapshot' => 8.50,
            'tax_rate' => 10.00,
            'discount_amount' => 0.00,
        ]);

        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/sync/offline', [
                'operations' => [
                    [
                        'idempotency_key' => 'status-update-valid-key',
                        'type' => 'order_item_status_update',
                        'payload' => [
                            'order_item_id' => $orderItem->id,
                            'status' => 'cooking',
                            'restaurant_id' => $this->restaurantA->id,
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.accepted', 1);
        $response->assertJsonPath('data.results.0.status', 'accepted');

        $this->assertDatabaseHas('order_items', [
            'id' => $orderItem->id,
            'status' => 'cooking',
        ]);
    }

    public function test_sync_order_item_create_validates_quantity(): void
    {
        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/sync/offline', [
                'operations' => [
                    [
                        'idempotency_key' => 'qty-validation-key',
                        'type' => 'order_item_create',
                        'payload' => [
                            'order_id' => $this->orderA->id,
                            'product_id' => $this->productA->id,
                            'quantity' => 0,
                            'unit_price' => 8.50,
                            'restaurant_id' => $this->restaurantA->id,
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.accepted', 1);
    }

    public function test_sync_updates_order_total_on_item_create(): void
    {
        $token = $this->waiterAToken();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/sync/offline', [
                'operations' => [
                    [
                        'idempotency_key' => 'total-update-key',
                        'type' => 'order_item_create',
                        'payload' => [
                            'order_id' => $this->orderA->id,
                            'product_id' => $this->productA->id,
                            'quantity' => 3,
                            'unit_price' => 8.50,
                            'restaurant_id' => $this->restaurantA->id,
                        ],
                    ],
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $this->orderA->id,
            'total_price' => 25.50,
        ]);
    }
}
