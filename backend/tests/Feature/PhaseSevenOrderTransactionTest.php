<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PhaseSevenOrderTransactionTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerA;
    private User $ownerB;
    private User $waiterA;
    private User $waiterB;
    private User $superadmin;
    private Restaurant $restaurantA;
    private Restaurant $restaurantB;
    private Table $tableA;
    private Table $tableB;
    private Category $categoryA;
    private Category $categoryB;
    private Product $productA;
    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
            'password' => \Illuminate\Support\Facades\Hash::make('superadmin123'),
            'role' => 'superadmin',
        ]);

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
            'status' => 'free',
        ]);

        $this->tableB = Table::create([
            'restaurant_id' => $this->restaurantB->id,
            'number' => 'B-01',
            'status' => 'free',
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
    }

    private function waiterAToken(): string
    {
        return $this->waiterA->createToken('test')->plainTextToken;
    }

    private function waiterBToken(): string
    {
        return $this->waiterB->createToken('test')->plainTextToken;
    }

    private function superadminToken(): string
    {
        return $this->superadmin->createToken('test')->plainTextToken;
    }

    public function test_client_can_create_order_from_session_token(): void
    {
        $response = $this->postJson('/api/v1/client/orders', [
            'session_token' => $this->tableA->secret_token,
            'restaurant_slug' => 'qa-a',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.restaurant_id', $this->restaurantA->id);
        $response->assertJsonPath('data.table_id', $this->tableA->id);
        $response->assertJsonPath('data.status', 'open');
        $response->assertJsonPath('data.total_price', '0.00');
        $this->assertDatabaseHas('orders', [
            'table_id' => $this->tableA->id,
            'status' => 'open',
            'restaurant_id' => $this->restaurantA->id,
        ]);
    }

    public function test_client_receives_existing_open_order(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session',
            'status' => 'open',
            'total_price' => 10.00,
        ]);

        $response = $this->postJson('/api/v1/client/orders', [
            'session_token' => $this->tableA->secret_token,
            'restaurant_slug' => 'qa-a',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $order->id);
        $response->assertJsonPath('data.total_price', '10.00');

        $orderCount = Order::where('table_id', $this->tableA->id)->count();
        $this->assertEquals(1, $orderCount);
    }

    public function test_client_can_append_items_to_order_with_snapshots(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session',
            'status' => 'open',
            'total_price' => 0.00,
        ]);

        $response = $this->withHeader('X-Idempotency-Key', 'idempotent-key-1')
            ->postJson('/api/v1/client/orders/' . $order->id . '/items', [
                'items' => [
                    [
                        'product_id' => $this->productA->id,
                        'quantity' => 2,
                        'unit_price' => 8.50,
                        'notes' => 'Extra sauce',
                        'target_area' => 'kitchen',
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_price', '17.00');
        $response->assertJsonPath('data.items.0.price_snapshot', '8.50');
        $response->assertJsonPath('data.items.0.tax_rate', '10.00');
        $response->assertJsonPath('data.items.0.discount_amount', '0.00');

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 2,
            'unit_price' => 8.50,
            'price_snapshot' => 8.50,
            'tax_rate' => 10.00,
        ]);
    }

    public function test_idempotency_key_prevents_duplicate_items(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session',
            'status' => 'open',
            'total_price' => 0.00,
        ]);

        $this->withHeader('X-Idempotency-Key', 'idempotent-key-dup')
            ->postJson('/api/v1/client/orders/' . $order->id . '/items', [
                'items' => [
                    [
                        'product_id' => $this->productA->id,
                        'quantity' => 1,
                        'unit_price' => 8.50,
                    ],
                ],
            ]);

        $response = $this->withHeader('X-Idempotency-Key', 'idempotent-key-dup')
            ->postJson('/api/v1/client/orders/' . $order->id . '/items', [
                'items' => [
                    [
                        'product_id' => $this->productA->id,
                        'quantity' => 1,
                        'unit_price' => 8.50,
                    ],
                ],
            ]);

        $response->assertStatus(200);

        $itemCount = OrderItem::where('order_id', $order->id)->count();
        $this->assertEquals(1, $itemCount);
    }

    public function test_snapshot_preserves_price_when_product_price_changes(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session',
            'status' => 'open',
            'total_price' => 0.00,
        ]);

        $this->postJson('/api/v1/client/orders/' . $order->id . '/items', [
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 1,
                    'unit_price' => 8.50,
                ],
            ],
        ]);

        $this->productA->update(['price' => 15.00]);

        $orderItem = OrderItem::where('order_id', $order->id)->first();

        $this->assertEquals(8.50, (float) $orderItem->price_snapshot);
        $this->assertEquals(8.50, (float) $orderItem->unit_price);
    }

    public function test_staff_can_close_order_and_free_table(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session',
            'status' => 'open',
            'total_price' => 25.00,
        ]);

        $orderItem = OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'test-close-item',
            'price_snapshot' => 8.50,
            'tax_rate' => 10.00,
            'discount_amount' => 0.00,
        ]);

        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/orders/' . $order->id . '/close');

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'closed');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'closed',
        ]);

        $this->assertDatabaseHas('tables', [
            'id' => $this->tableA->id,
            'status' => 'free',
        ]);
    }

    public function test_closing_order_updates_all_pending_items_to_delivered(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session',
            'status' => 'open',
            'total_price' => 25.00,
        ]);

        OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'test-close-item-1',
            'price_snapshot' => 8.50,
            'tax_rate' => 10.00,
            'discount_amount' => 0.00,
        ]);

        OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 2,
            'unit_price' => 8.50,
            'status' => 'cooking',
            'target_area' => 'kitchen',
            'idempotency_key' => 'test-close-item-2',
            'price_snapshot' => 8.50,
            'tax_rate' => 10.00,
            'discount_amount' => 0.00,
        ]);

        $token = $this->waiterAToken();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/orders/' . $order->id . '/close');

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'status' => 'delivered',
        ]);
    }

    public function test_cannot_append_to_closed_order(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session',
            'status' => 'closed',
            'total_price' => 0.00,
        ]);

        $response = $this->postJson('/api/v1/client/orders/' . $order->id . '/items', [
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 1,
                    'unit_price' => 8.50,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Order is not open.');
    }

    public function test_tenant_isolation_cannot_append_items_from_another_restaurant(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurantB->id,
            'table_id' => $this->tableB->id,
            'session_token' => 'test-session-b',
            'status' => 'open',
            'total_price' => 0.00,
        ]);

        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/client/orders/' . $order->id . '/items', [
                'items' => [
                    [
                        'product_id' => $this->productA->id,
                        'quantity' => 1,
                        'unit_price' => 8.50,
                    ],
                ],
            ]);

        $response->assertStatus(404);
    }

    public function test_staff_from_different_restaurant_cannot_close_order(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurantB->id,
            'table_id' => $this->tableB->id,
            'session_token' => 'test-session-b',
            'status' => 'open',
            'total_price' => 25.00,
        ]);

        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/orders/' . $order->id . '/close');

        $response->assertStatus(404);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'open',
        ]);
    }

    public function test_validation_rejects_missing_items(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session',
            'status' => 'open',
            'total_price' => 0.00,
        ]);

        $response = $this->postJson('/api/v1/client/orders/' . $order->id . '/items', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_validation_rejects_invalid_quantity(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session',
            'status' => 'open',
            'total_price' => 0.00,
        ]);

        $response = $this->postJson('/api/v1/client/orders/' . $order->id . '/items', [
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 0,
                    'unit_price' => 8.50,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    public function test_validation_rejects_negative_unit_price(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session',
            'status' => 'open',
            'total_price' => 0.00,
        ]);

        $response = $this->postJson('/api/v1/client/orders/' . $order->id . '/items', [
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 1,
                    'unit_price' => -5.00,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.unit_price']);
    }

    public function test_order_total_price_is_updated_after_appending_items(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session',
            'status' => 'open',
            'total_price' => 0.00,
        ]);

        $this->postJson('/api/v1/client/orders/' . $order->id . '/items', [
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 3,
                    'unit_price' => 8.50,
                ],
            ],
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'total_price' => 25.50,
        ]);
    }

    public function test_multiple_items_can_be_appended_at_once(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session',
            'status' => 'open',
            'total_price' => 0.00,
        ]);

        $productC = Product::create([
            'restaurant_id' => $this->restaurantA->id,
            'category_id' => $this->categoryA->id,
            'name' => ['en' => 'Tapa'],
            'price' => 5.00,
        ]);

        $response = $this->postJson('/api/v1/client/orders/' . $order->id . '/items', [
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 2,
                    'unit_price' => 8.50,
                    'target_area' => 'kitchen',
                ],
                [
                    'product_id' => $productC->id,
                    'quantity' => 3,
                    'unit_price' => 5.00,
                    'target_area' => 'bar',
                ],
            ],
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 2,
            'target_area' => 'kitchen',
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $productC->id,
            'quantity' => 3,
            'target_area' => 'bar',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'total_price' => 32.00,
        ]);
    }

    public function test_close_order_returns_items_with_snapshots(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session',
            'status' => 'open',
            'total_price' => 17.00,
        ]);

        OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 2,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'test-close-snapshot',
            'price_snapshot' => 8.50,
            'tax_rate' => 10.00,
            'discount_amount' => 0.00,
        ]);

        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/orders/' . $order->id . '/close');

        $response->assertStatus(200);
        $response->assertJsonPath('data.items.0.price_snapshot', '8.50');
        $response->assertJsonPath('data.items.0.tax_rate', '10.00');
        $response->assertJsonPath('data.items.0.discount_amount', '0.00');
    }

    public function test_transaction_atomicity_on_order_creation(): void
    {
        $originalTableCount = Table::where('id', $this->tableA->id)->count();

        $response = $this->postJson('/api/v1/client/orders', [
            'session_token' => $this->tableA->secret_token,
            'restaurant_slug' => 'qa-a',
        ]);

        $response->assertStatus(201);

        $this->assertEquals(1, Table::where('id', $this->tableA->id)->count());
        $this->assertDatabaseHas('orders', [
            'table_id' => $this->tableA->id,
            'restaurant_id' => $this->restaurantA->id,
        ]);
    }

    public function test_invalid_session_token_returns_404(): void
    {
        $response = $this->postJson('/api/v1/client/orders', [
            'session_token' => 'invalid-token-12345',
            'restaurant_slug' => 'qa-a',
        ]);

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'Invalid session token.');
    }

    public function test_closed_order_cannot_be_closed_again(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session',
            'status' => 'closed',
            'total_price' => 25.00,
        ]);

        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/orders/' . $order->id . '/close');

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Order is already closed.');
    }
}
