<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\OrderStateChanged;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PhaseFiveStaffControlTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerA;
    private User $ownerB;
    private User $kitchenA;
    private User $kitchenB;
    private User $waiterA;
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
    private OrderItem $orderItemA;
    private OrderItem $orderItemB;

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

        $this->kitchenA = User::create([
            'name' => 'Kitchen A',
            'email' => 'kitchen.a@example.test',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'role' => 'kitchen',
        ]);

        $this->kitchenB = User::create([
            'name' => 'Kitchen B',
            'email' => 'kitchen.b@example.test',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'role' => 'kitchen',
        ]);

        $this->waiterA = User::create([
            'name' => 'Waiter A',
            'email' => 'waiter.a@example.test',
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
        $this->kitchenA->update(['restaurant_id' => $this->restaurantA->id]);
        $this->kitchenB->update(['restaurant_id' => $this->restaurantB->id]);
        $this->waiterA->update(['restaurant_id' => $this->restaurantA->id]);

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

        $this->orderItemA = OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $this->orderA->id,
            'product_id' => $this->productA->id,
            'quantity' => 2,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'test-item-a',
        ]);

        $this->orderItemB = OrderItem::create([
            'restaurant_id' => $this->restaurantB->id,
            'order_id' => $this->orderB->id,
            'product_id' => $this->productB->id,
            'quantity' => 1,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'test-item-b',
        ]);
    }

    private function kitchenAToken(): string
    {
        return $this->kitchenA->createToken('test')->plainTextToken;
    }

    private function kitchenBToken(): string
    {
        return $this->kitchenB->createToken('test')->plainTextToken;
    }

    private function waiterAToken(): string
    {
        return $this->waiterA->createToken('test')->plainTextToken;
    }

    public function test_kitchen_can_transition_pending_to_cooking(): void
    {
        Event::fake([OrderStateChanged::class]);

        $token = $this->kitchenAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/staff/order-items/' . $this->orderItemA->id . '/status', [
                'status' => 'cooking',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'cooking');
        $response->assertJsonPath('data.previous_status', 'pending');

        $this->assertDatabaseHas('order_items', [
            'id' => $this->orderItemA->id,
            'status' => 'cooking',
        ]);

        Event::assertDispatched(OrderStateChanged::class);
    }

    public function test_kitchen_can_transition_cooking_to_ready(): void
    {
        $this->orderItemA->update(['status' => 'cooking']);

        Event::fake([OrderStateChanged::class]);

        $token = $this->kitchenAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/staff/order-items/' . $this->orderItemA->id . '/status', [
                'status' => 'ready',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'ready');
        $response->assertJsonPath('data.previous_status', 'cooking');

        $this->assertDatabaseHas('order_items', [
            'id' => $this->orderItemA->id,
            'status' => 'ready',
        ]);
    }

    public function test_kitchen_can_transition_ready_to_delivered(): void
    {
        $this->orderItemA->update(['status' => 'ready']);

        Event::fake([OrderStateChanged::class]);

        $token = $this->kitchenAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/staff/order-items/' . $this->orderItemA->id . '/status', [
                'status' => 'delivered',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'delivered');

        $this->assertDatabaseHas('order_items', [
            'id' => $this->orderItemA->id,
            'status' => 'delivered',
        ]);
    }

    public function test_kitchen_can_cancel_from_pending(): void
    {
        Event::fake([OrderStateChanged::class]);

        $token = $this->kitchenAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/staff/order-items/' . $this->orderItemA->id . '/status', [
                'status' => 'cancelled',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('order_items', [
            'id' => $this->orderItemA->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_kitchen_can_cancel_from_ready(): void
    {
        $this->orderItemA->update(['status' => 'ready']);

        Event::fake([OrderStateChanged::class]);

        $token = $this->kitchenAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/staff/order-items/' . $this->orderItemA->id . '/status', [
                'status' => 'cancelled',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'cancelled');
    }

    public function test_invalid_transition_pending_to_ready_is_rejected(): void
    {
        $token = $this->kitchenAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/staff/order-items/' . $this->orderItemA->id . '/status', [
                'status' => 'ready',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Invalid transition from pending to ready.');

        $this->assertDatabaseHas('order_items', [
            'id' => $this->orderItemA->id,
            'status' => 'pending',
        ]);
    }

    public function test_invalid_transition_pending_to_delivered_is_rejected(): void
    {
        $token = $this->kitchenAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/staff/order-items/' . $this->orderItemA->id . '/status', [
                'status' => 'delivered',
            ]);

        $response->assertStatus(422);
    }

    public function test_invalid_transition_delivered_to_cooking_is_rejected(): void
    {
        $this->orderItemA->update(['status' => 'delivered']);

        $token = $this->kitchenAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/staff/order-items/' . $this->orderItemA->id . '/status', [
                'status' => 'cooking',
            ]);

        $response->assertStatus(422);
    }

    public function test_invalid_transition_cancelled_to_anything_is_rejected(): void
    {
        $this->orderItemA->update(['status' => 'cancelled']);

        $token = $this->kitchenAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/staff/order-items/' . $this->orderItemA->id . '/status', [
                'status' => 'cooking',
            ]);

        $response->assertStatus(422);
    }

    public function test_owner_cannot_change_status_of_another_restaurant_item(): void
    {
        $token = $this->kitchenBToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/staff/order-items/' . $this->orderItemA->id . '/status', [
                'status' => 'cooking',
            ]);

        $response->assertStatus(404);

        $this->assertDatabaseHas('order_items', [
            'id' => $this->orderItemA->id,
            'status' => 'pending',
        ]);
    }

    public function test_unauthenticated_user_cannot_change_status(): void
    {
        $response = $this->putJson('/api/v1/staff/order-items/' . $this->orderItemA->id . '/status', [
            'status' => 'cooking',
        ]);

        $response->assertStatus(401);
    }

    public function test_pending_items_endpoint_returns_correct_items(): void
    {
        $token = $this->kitchenAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/staff/order-items/pending?area=kitchen');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertNotEmpty($data);

        foreach ($data as $item) {
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('order_id', $item);
            $this->assertArrayHasKey('table_number', $item);
            $this->assertArrayHasKey('product_name', $item);
            $this->assertArrayHasKey('quantity', $item);
            $this->assertArrayHasKey('status', $item);
            $this->assertArrayHasKey('target_area', $item);
            $this->assertEquals('kitchen', $item['target_area']);
            $this->assertContains($item['status'], ['pending', 'cooking', 'ready']);
        }
    }

    public function test_pending_items_endpoint_filters_by_area(): void
    {
        OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $this->orderA->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'bar',
            'idempotency_key' => 'test-bar-item',
        ]);

        $token = $this->kitchenAToken();

        $kitchenResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/staff/order-items/pending?area=kitchen');

        $barResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/staff/order-items/pending?area=bar');

        $kitchenResponse->assertStatus(200);
        $barResponse->assertStatus(200);

        $kitchenItems = $kitchenResponse->json('data');
        $barItems = $barResponse->json('data');

        foreach ($kitchenItems as $item) {
            $this->assertEquals('kitchen', $item['target_area']);
        }

        foreach ($barItems as $item) {
            $this->assertEquals('bar', $item['target_area']);
        }
    }

    public function test_order_state_event_is_dispatched_on_status_change(): void
    {
        Event::fake([OrderStateChanged::class]);

        $token = $this->kitchenAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/staff/order-items/' . $this->orderItemA->id . '/status', [
                'status' => 'cooking',
            ]);

        $response->assertStatus(200);

        Event::assertDispatched(OrderStateChanged::class, function ($event) {
            $this->assertEquals($this->orderItemA->id, $event->orderItem->id);
            $this->assertEquals('pending', $event->previousStatus);
            $this->assertEquals('cooking', $event->orderItem->status);

            return true;
        });
    }

    public function test_bulk_update_updates_multiple_items(): void
    {
        $orderItemC = OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $this->orderA->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'test-bulk-item-1',
        ]);

        $token = $this->kitchenAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/staff/order-items/bulk', [
                'items' => [
                    ['id' => $this->orderItemA->id, 'status' => 'cooking'],
                    ['id' => $orderItemC->id, 'status' => 'cooking'],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('updated', 2);
        $response->assertJsonPath('failed', 0);

        $this->assertDatabaseHas('order_items', [
            'id' => $this->orderItemA->id,
            'status' => 'cooking',
        ]);

        $this->assertDatabaseHas('order_items', [
            'id' => $orderItemC->id,
            'status' => 'cooking',
        ]);
    }

    public function test_bulk_update_rejects_invalid_transitions(): void
    {
        $orderItemC = OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $this->orderA->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'test-bulk-item-2',
        ]);

        $token = $this->kitchenAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/staff/order-items/bulk', [
                'items' => [
                    ['id' => $this->orderItemA->id, 'status' => 'cooking'],
                    ['id' => $orderItemC->id, 'status' => 'delivered'],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('updated', 1);
        $response->assertJsonPath('failed', 1);

        $this->assertDatabaseHas('order_items', [
            'id' => $this->orderItemA->id,
            'status' => 'cooking',
        ]);

        $this->assertDatabaseHas('order_items', [
            'id' => $orderItemC->id,
            'status' => 'pending',
        ]);
    }

    public function test_bulk_update_requires_items_array(): void
    {
        $token = $this->kitchenAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/staff/order-items/bulk', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_update_status_requires_valid_status(): void
    {
        $token = $this->kitchenAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/staff/order-items/' . $this->orderItemA->id . '/status', [
                'status' => 'invalid_status',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_update_status_requires_status_field(): void
    {
        $token = $this->kitchenAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/staff/order-items/' . $this->orderItemA->id . '/status', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_complete_lifecycle_pending_to_delivered(): void
    {
        $token = $this->kitchenAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/staff/order-items/' . $this->orderItemA->id . '/status', [
                'status' => 'cooking',
            ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('order_items', ['id' => $this->orderItemA->id, 'status' => 'cooking']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/staff/order-items/' . $this->orderItemA->id . '/status', [
                'status' => 'ready',
            ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('order_items', ['id' => $this->orderItemA->id, 'status' => 'ready']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/staff/order-items/' . $this->orderItemA->id . '/status', [
                'status' => 'delivered',
            ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('order_items', ['id' => $this->orderItemA->id, 'status' => 'delivered']);
    }

    public function test_complete_lifecycle_pending_to_cancelled(): void
    {
        $token = $this->kitchenAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/staff/order-items/' . $this->orderItemA->id . '/status', [
                'status' => 'cancelled',
            ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('order_items', ['id' => $this->orderItemA->id, 'status' => 'cancelled']);
    }

    public function test_no_double_booking_on_concurrent_status_changes(): void
    {
        $this->orderItemA->update(['status' => 'pending']);

        Event::fake([OrderStateChanged::class]);

        $token = $this->kitchenAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/staff/order-items/' . $this->orderItemA->id . '/status', [
                'status' => 'cooking',
            ]);
        $response->assertStatus(200);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/staff/order-items/' . $this->orderItemA->id . '/status', [
                'status' => 'delivered',
            ]);
        $response->assertStatus(422);

        $this->assertDatabaseHas('order_items', [
            'id' => $this->orderItemA->id,
            'status' => 'cooking',
        ]);
    }

    public function test_waiter_can_change_status(): void
    {
        Event::fake([OrderStateChanged::class]);

        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/staff/order-items/' . $this->orderItemA->id . '/status', [
                'status' => 'cooking',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('order_items', [
            'id' => $this->orderItemA->id,
            'status' => 'cooking',
        ]);

        Event::assertDispatched(OrderStateChanged::class);
    }

    public function test_order_item_resource_includes_all_fields(): void
    {
        $token = $this->kitchenAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/staff/order-items/pending?area=kitchen');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertNotEmpty($data);

        $item = $data[0];
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('order_id', $item);
        $this->assertArrayHasKey('order_number', $item);
        $this->assertArrayHasKey('table_number', $item);
        $this->assertArrayHasKey('product_id', $item);
        $this->assertArrayHasKey('product_name', $item);
        $this->assertArrayHasKey('quantity', $item);
        $this->assertArrayHasKey('unit_price', $item);
        $this->assertArrayHasKey('notes', $item);
        $this->assertArrayHasKey('status', $item);
        $this->assertArrayHasKey('target_area', $item);
        $this->assertArrayHasKey('created_at', $item);
        $this->assertArrayHasKey('updated_at', $item);
    }
}
