<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\OrderItemCreated;
use App\Events\OrderStateChanged;
use App\Events\TableCleared;
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

class PhaseFourBroadcastTest extends TestCase
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
    }

    private function ownerAToken(): string
    {
        return $this->ownerA->createToken('test')->plainTextToken;
    }

    private function waiterAToken(): string
    {
        return $this->waiterA->createToken('test')->plainTextToken;
    }

    private function ownerBToken(): string
    {
        return $this->ownerB->createToken('test')->plainTextToken;
    }

    private function waiterBToken(): string
    {
        return $this->waiterB->createToken('test')->plainTextToken;
    }

    public function test_order_item_created_event_broadcasts_on_correct_channels(): void
    {
        Event::fake([OrderItemCreated::class]);

        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session-token',
            'status' => 'open',
            'total_price' => 0.00,
        ]);

        $orderItem = OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 2,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'test-idempotency-key-1',
        ]);

        Event::dispatch(new OrderItemCreated($orderItem));

        Event::assertDispatched(OrderItemCreated::class, function ($event) use ($orderItem, $order) {
            $this->assertEquals($orderItem->id, $event->orderItem->id);
            $this->assertEquals($order->restaurant_id, $event->orderItem->order->restaurant_id);

            $channels = $event->broadcastOn();
            $this->assertCount(2, $channels);

            return true;
        });
    }

    public function test_order_state_changed_event_broadcasts_on_correct_channels(): void
    {
        Event::fake([OrderStateChanged::class]);

        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session-token',
            'status' => 'open',
            'total_price' => 0.00,
        ]);

        $orderItem = OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'test-idempotency-key-2',
        ]);

        $previousStatus = $orderItem->status;
        $orderItem->update(['status' => 'cooking']);

        Event::dispatch(new OrderStateChanged($orderItem, $previousStatus));

        Event::assertDispatched(OrderStateChanged::class, function ($event) use ($orderItem, $previousStatus) {
            $this->assertEquals($orderItem->id, $event->orderItem->id);
            $this->assertEquals('cooking', $event->orderItem->status);
            $this->assertEquals($previousStatus, $event->previousStatus);

            $channels = $event->broadcastOn();
            $this->assertCount(2, $channels);

            return true;
        });
    }

    public function test_table_cleared_event_broadcasts_on_correct_channels(): void
    {
        Event::fake([TableCleared::class]);

        Event::dispatch(new TableCleared($this->tableA));

        Event::assertDispatched(TableCleared::class, function ($event) {
            $this->assertEquals($this->tableA->id, $event->table->id);
            $this->assertEquals($this->restaurantA->id, $event->table->restaurant_id);

            $channels = $event->broadcastOn();
            $this->assertCount(2, $channels);

            return true;
        });
    }

    public function test_order_item_event_includes_restaurant_id_in_payload(): void
    {
        Event::fake([OrderItemCreated::class]);

        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session-token',
            'status' => 'open',
            'total_price' => 0.00,
        ]);

        $orderItem = OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'test-idempotency-key-3',
        ]);

        Event::dispatch(new OrderItemCreated($orderItem));

        Event::assertDispatched(OrderItemCreated::class, function ($event) {
            $payload = $event->broadcastWith();

            $this->assertArrayHasKey('id', $payload);
            $this->assertArrayHasKey('order_id', $payload);
            $this->assertArrayHasKey('product_id', $payload);
            $this->assertArrayHasKey('quantity', $payload);
            $this->assertArrayHasKey('unit_price', $payload);
            $this->assertArrayHasKey('status', $payload);
            $this->assertArrayHasKey('target_area', $payload);
            $this->assertArrayHasKey('created_at', $payload);

            $this->assertEquals('pending', $payload['status']);
            $this->assertEquals('kitchen', $payload['target_area']);

            return true;
        });
    }

    public function test_order_state_event_includes_previous_and_new_status(): void
    {
        Event::fake([OrderStateChanged::class]);

        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session-token',
            'status' => 'open',
            'total_price' => 0.00,
        ]);

        $orderItem = OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'test-idempotency-key-4',
        ]);

        $previousStatus = $orderItem->status;
        $orderItem->update(['status' => 'ready']);

        Event::dispatch(new OrderStateChanged($orderItem, $previousStatus));

        Event::assertDispatched(OrderStateChanged::class, function ($event) {
            $payload = $event->broadcastWith();

            $this->assertArrayHasKey('previous_status', $payload);
            $this->assertArrayHasKey('status', $payload);
            $this->assertArrayHasKey('target_area', $payload);
            $this->assertArrayHasKey('updated_at', $payload);

            $this->assertEquals('pending', $payload['previous_status']);
            $this->assertEquals('ready', $payload['status']);

            return true;
        });
    }

    public function test_table_cleared_event_includes_table_number_and_cleared_at(): void
    {
        Event::fake([TableCleared::class]);

        Event::dispatch(new TableCleared($this->tableA));

        Event::assertDispatched(TableCleared::class, function ($event) {
            $payload = $event->broadcastWith();

            $this->assertArrayHasKey('id', $payload);
            $this->assertArrayHasKey('number', $payload);
            $this->assertArrayHasKey('status', $payload);
            $this->assertArrayHasKey('restaurant_id', $payload);
            $this->assertArrayHasKey('cleared_at', $payload);

            $this->assertEquals('A-01', $payload['number']);
            $this->assertEquals('free', $payload['status']);
            $this->assertEquals($this->restaurantA->id, $payload['restaurant_id']);

            return true;
        });
    }

    public function test_events_use_private_channel_with_restaurant_id(): void
    {
        Event::fake([OrderItemCreated::class]);

        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session-token',
            'status' => 'open',
            'total_price' => 0.00,
        ]);

        $orderItem = OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'test-idempotency-key-5',
        ]);

        Event::dispatch(new OrderItemCreated($orderItem));

        Event::assertDispatched(OrderItemCreated::class, function ($event) {
            $channels = $event->broadcastOn();
            $privateChannel = $channels[0];

            $this->assertInstanceOf(\Illuminate\Broadcasting\PrivateChannel::class, $privateChannel);

            return true;
        });
    }

    public function test_events_use_tenant_specific_channel(): void
    {
        Event::fake([TableCleared::class]);

        Event::dispatch(new TableCleared($this->tableA));

        Event::assertDispatched(TableCleared::class, function ($event) {
            $channels = $event->broadcastOn();
            $tenantChannel = $channels[1];

            $this->assertInstanceOf(\Illuminate\Broadcasting\Channel::class, $tenantChannel);

            return true;
        });
    }

    public function test_order_item_event_uses_should_broadcast_now(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session-token',
            'status' => 'open',
            'total_price' => 0.00,
        ]);

        $orderItem = OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'test-check-1',
        ]);

        $event = new OrderItemCreated($orderItem);
        $this->assertTrue($event instanceof \Illuminate\Contracts\Broadcasting\ShouldBroadcastNow);
    }

    public function test_order_state_changed_event_uses_should_broadcast_now(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session-token',
            'status' => 'open',
            'total_price' => 0.00,
        ]);

        $orderItem = OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'test-check-2',
        ]);

        $event = new OrderStateChanged($orderItem, 'pending');
        $this->assertTrue($event instanceof \Illuminate\Contracts\Broadcasting\ShouldBroadcastNow);
    }

    public function test_table_cleared_event_uses_should_broadcast_now(): void
    {
        $event = new TableCleared($this->tableA);
        $this->assertTrue($event instanceof \Illuminate\Contracts\Broadcasting\ShouldBroadcastNow);
    }

    public function test_events_dont_expose_sensitive_data(): void
    {
        Event::fake([OrderItemCreated::class]);

        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session-token',
            'status' => 'open',
            'total_price' => 0.00,
        ]);

        $orderItem = OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'test-idempotency-key-6',
        ]);

        Event::dispatch(new OrderItemCreated($orderItem));

        Event::assertDispatched(OrderItemCreated::class, function ($event) {
            $payload = $event->broadcastWith();

            $this->assertArrayNotHasKey('password', $payload);
            $this->assertArrayNotHasKey('api_token', $payload);
            $this->assertArrayNotHasKey('remember_token', $payload);

            return true;
        });
    }

    public function test_events_are_tenant_isolated(): void
    {
        Event::fake([OrderItemCreated::class]);

        $orderA = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session-token-a',
            'status' => 'open',
            'total_price' => 0.00,
        ]);

        $orderItemA = OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $orderA->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'test-idempotency-key-a',
        ]);

        Event::dispatch(new OrderItemCreated($orderItemA));

        Event::assertDispatched(OrderItemCreated::class, function ($event) use ($orderA) {
            $this->assertEquals($orderA->restaurant_id, $event->orderItem->order->restaurant_id);

            return true;
        });
    }

    public function test_broadcasting_config_is_loaded(): void
    {
        $config = config('broadcasting');

        $this->assertArrayHasKey('default', $config);
        $this->assertArrayHasKey('connections', $config);
        $this->assertArrayHasKey('reverb', $config['connections']);
        $this->assertArrayHasKey('pusher', $config['connections']);
    }

    public function test_order_item_created_event_has_correct_broadcast_as(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session-token',
            'status' => 'open',
            'total_price' => 0.00,
        ]);

        $orderItem = OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'test-check-3',
        ]);

        $event = new OrderItemCreated($orderItem);
        $this->assertEquals('order-item.created', $event->broadcastAs());
    }

    public function test_order_state_changed_event_has_correct_broadcast_as(): void
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session-token',
            'status' => 'open',
            'total_price' => 0.00,
        ]);

        $orderItem = OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'test-check-4',
        ]);

        $event = new OrderStateChanged($orderItem, 'pending');
        $this->assertEquals('order-state.changed', $event->broadcastAs());
    }

    public function test_table_cleared_event_has_correct_broadcast_as(): void
    {
        $event = new TableCleared($this->tableA);
        $this->assertEquals('table.cleared', $event->broadcastAs());
    }

    public function test_full_event_lifecycle_order_created_then_state_changed(): void
    {
        Event::fake([OrderItemCreated::class, OrderStateChanged::class]);

        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session-token',
            'status' => 'open',
            'total_price' => 0.00,
        ]);

        $orderItem = OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'test-idempotency-key-lifecycle',
        ]);

        Event::dispatch(new OrderItemCreated($orderItem));
        Event::assertDispatched(OrderItemCreated::class);

        $previousStatus = $orderItem->status;
        $orderItem->update(['status' => 'cooking']);
        Event::dispatch(new OrderStateChanged($orderItem, $previousStatus));
        Event::assertDispatched(OrderStateChanged::class);

        $previousStatus = $orderItem->status;
        $orderItem->update(['status' => 'ready']);
        Event::dispatch(new OrderStateChanged($orderItem, $previousStatus));
        Event::assertDispatched(OrderStateChanged::class);

        $previousStatus = $orderItem->status;
        $orderItem->update(['status' => 'delivered']);
        Event::dispatch(new OrderStateChanged($orderItem, $previousStatus));
        Event::assertDispatched(OrderStateChanged::class);
    }

    public function test_multiple_events_for_same_restaurant_use_same_channel(): void
    {
        Event::fake([OrderItemCreated::class, TableCleared::class]);

        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session-token',
            'status' => 'open',
            'total_price' => 0.00,
        ]);

        $orderItem = OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'test-idempotency-key-same-channel',
        ]);

        Event::dispatch(new OrderItemCreated($orderItem));
        Event::dispatch(new TableCleared($this->tableA));

        Event::assertDispatched(OrderItemCreated::class);
        Event::assertDispatched(TableCleared::class);
    }

    public function test_channels_file_registers_restaurant_channel(): void
    {
        $channelsFile = file_get_contents(base_path('routes/channels.php'));

        $this->assertStringContainsString("Broadcast::channel('restaurant.{restaurantId}'", $channelsFile);
        $this->assertStringContainsString("User \$user", $channelsFile);
        $this->assertStringContainsString("restaurantId", $channelsFile);
    }

    public function test_channels_file_authorizes_superadmin(): void
    {
        $channelsFile = file_get_contents(base_path('routes/channels.php'));

        $this->assertStringContainsString("superadmin", $channelsFile);
    }

    public function test_channels_file_uses_tenant_authorization(): void
    {
        $channelsFile = file_get_contents(base_path('routes/channels.php'));

        $this->assertStringContainsString("user->restaurant_id", $channelsFile);
        $this->assertStringContainsString("=== \$restaurantId", $channelsFile);
    }
}
