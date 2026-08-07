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
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseElevenAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerA;
    private User $ownerB;
    private Restaurant $restaurantA;
    private Restaurant $restaurantB;
    private Category $categoryA;
    private Product $productA;
    private Product $productB;
    private Table $tableA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerA = User::create([
            'name' => 'Owner A',
            'email' => 'owner.a@example.test',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);

        $this->ownerB = User::create([
            'name' => 'Owner B',
            'email' => 'owner.b@example.test',
            'password' => Hash::make('password123'),
            'role' => 'owner',
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

        $this->ownerA->update(['restaurant_id' => $this->restaurantA->id]);
        $this->ownerB->update(['restaurant_id' => $this->restaurantB->id]);

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

        $this->productB = Product::create([
            'restaurant_id' => $this->restaurantA->id,
            'category_id' => $this->categoryA->id,
            'name' => ['en' => 'Tapa'],
            'price' => 5.00,
        ]);

        $this->tableA = Table::create([
            'restaurant_id' => $this->restaurantA->id,
            'number' => 'A-01',
        ]);
    }

    private function ownerAToken(): string
    {
        return $this->ownerA->createToken('test')->plainTextToken;
    }

    private function ownerBToken(): string
    {
        return $this->ownerB->createToken('test')->plainTextToken;
    }

    private function createClosedOrder(float $totalPrice, string $createdAt = '2024-01-15 12:00:00'): Order
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'analytics-session-' . uniqid(),
            'status' => 'closed',
            'total_price' => $totalPrice,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $product = $this->productA;
        OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $totalPrice,
            'status' => 'delivered',
            'target_area' => 'kitchen',
            'idempotency_key' => 'analytics-item-' . $order->id,
            'price_snapshot' => $totalPrice,
            'tax_rate' => 10.00,
            'discount_amount' => 0.00,
        ]);

        return $order;
    }

    private function createOrderWithItems(array $items, string $createdAt = '2024-01-15 12:00:00'): Order
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'analytics-order-' . uniqid(),
            'status' => 'closed',
            'total_price' => 0.00,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $totalPrice = 0.0;
        foreach ($items as $item) {
            OrderItem::create([
                'restaurant_id' => $this->restaurantA->id,
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'status' => 'delivered',
                'target_area' => 'kitchen',
                'idempotency_key' => 'analytics-order-item-' . $order->id . '-' . $item['product_id'],
                'price_snapshot' => $item['unit_price'],
                'tax_rate' => 10.00,
                'discount_amount' => 0.00,
            ]);
            $totalPrice += $item['unit_price'] * $item['quantity'];
        }

        $order->update(['total_price' => $totalPrice]);

        return $order;
    }

    private function createOrderForRestaurant(Restaurant $restaurant, callable $callback): void
    {
        $callback($restaurant);
    }

    public function test_summary_returns_correct_metrics(): void
    {
        $this->createClosedOrder(17.00, '2024-01-15 12:00:00');
        $this->createClosedOrder(25.50, '2024-01-15 13:00:00');
        $this->createClosedOrder(12.00, '2024-01-16 12:00:00');

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/analytics/summary');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals(3, $data['total_orders']);
        $this->assertEquals(54.50, $data['total_revenue']);
        $this->assertGreaterThan(0, $data['total_items_sold']);
    }

    public function test_summary_computes_avg_ticket(): void
    {
        $this->createClosedOrder(10.00, '2024-01-15 12:00:00');
        $this->createClosedOrder(20.00, '2024-01-15 13:00:00');

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/analytics/summary');

        $response->assertStatus(200);

        $this->assertEquals(15.00, $response->json('data.avg_ticket'));
    }

    public function test_summary_filters_by_date_range(): void
    {
        $this->createClosedOrder(10.00, '2024-01-15 12:00:00');
        $this->createClosedOrder(20.00, '2024-01-20 12:00:00');
        $this->createClosedOrder(30.00, '2024-01-25 12:00:00');

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/analytics/summary?start_date=2024-01-16&end_date=2024-01-24');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.total_orders'));
        $this->assertEquals(20.00, $response->json('data.total_revenue'));
    }

    public function test_summary_returns_empty_for_no_orders(): void
    {
        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/analytics/summary');

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('data.total_orders'));
        $this->assertEquals(0.00, $response->json('data.total_revenue'));
    }

    public function test_top_products_returns_sorted_by_quantity(): void
    {
        $this->createOrderWithItems([
            ['product_id' => $this->productA->id, 'quantity' => 5, 'unit_price' => 8.50],
        ]);

        $this->createOrderWithItems([
            ['product_id' => $this->productB->id, 'quantity' => 3, 'unit_price' => 5.00],
        ]);

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/analytics/top-products');

        $response->assertStatus(200);

        $products = $response->json('data');
        $this->assertEquals(2, count($products));
        $this->assertEquals('Bruschetta', $products[0]['product_name']);
        $this->assertEquals(5, $products[0]['total_quantity']);
    }

    public function test_top_products_limits_results(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $product = Product::create([
                'restaurant_id' => $this->restaurantA->id,
                'category_id' => $this->categoryA->id,
                'name' => ['en' => "Product {$i}"],
                'price' => 10.00,
            ]);

            $this->createOrderWithItems([
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10.00],
            ]);
        }

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/analytics/top-products?limit=5');

        $response->assertStatus(200);
        $this->assertEquals(5, count($response->json('data')));
    }

    public function test_top_products_filters_by_date(): void
    {
        $this->createOrderWithItems([
            ['product_id' => $this->productA->id, 'quantity' => 5, 'unit_price' => 8.50],
        ], '2024-01-15 12:00:00');

        $this->createOrderWithItems([
            ['product_id' => $this->productA->id, 'quantity' => 10, 'unit_price' => 8.50],
        ], '2024-01-20 12:00:00');

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/analytics/top-products?start_date=2024-01-16&end_date=2024-01-21');

        $response->assertStatus(200);
        $this->assertEquals(10, $response->json('data.0.total_quantity'));
    }

    public function test_csv_export_streams_data(): void
    {
        $this->createClosedOrder(17.00, '2024-01-15 12:00:00');
        $this->createClosedOrder(25.50, '2024-01-15 13:00:00');

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/analytics/export/csv');

        $response->assertStatus(200);
        $contentType = $response->headers->get('Content-Type');
        $this->assertStringContainsString('text/csv', $contentType);
        $response->assertHeader('Content-Disposition', str_contains(
            $response->headers->get('Content-Disposition'),
            'attachment'
        ));

        $csvContent = $response->content();
        $this->assertStringContainsString('order_id', $csvContent);
        $this->assertStringContainsString('total_price', $csvContent);
    }

    public function test_csv_export_includes_order_data(): void
    {
        $this->createClosedOrder(17.00, '2024-01-15 12:00:00');

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/analytics/export/csv');

        $response->assertStatus(200);

        $csvContent = $response->content();
        $lines = explode("\n", trim($csvContent));

        $this->assertGreaterThan(1, count($lines));
        $this->assertStringContainsString('17.00', $csvContent);
    }

    public function test_analytics_tenant_isolation(): void
    {
        $this->createOrderForRestaurant($this->restaurantB, function ($rest) {
            $product = Product::create([
                'restaurant_id' => $rest->id,
                'category_id' => Category::create([
                    'restaurant_id' => $rest->id,
                    'name' => ['en' => 'Category'],
                ])->id,
                'name' => ['en' => 'B Product'],
                'price' => 15.00,
            ]);

            $order = Order::create([
                'restaurant_id' => $rest->id,
                'table_id' => Table::create([
                    'restaurant_id' => $rest->id,
                    'number' => 'B-01',
                ])->id,
                'session_token' => 'test-session-b',
                'status' => 'closed',
                'total_price' => 15.00,
            ]);

            OrderItem::create([
                'restaurant_id' => $rest->id,
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 15.00,
                'status' => 'delivered',
                'target_area' => 'kitchen',
                'idempotency_key' => 'tenant-isolation-item',
                'price_snapshot' => 15.00,
                'tax_rate' => 10.00,
                'discount_amount' => 0.00,
            ]);
        });

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/analytics/summary');

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('data.total_orders'));
    }

    public function test_summary_peak_hours_returns_data(): void
    {
        $this->createClosedOrder(10.00, '2024-01-15 12:00:00');
        $this->createClosedOrder(10.00, '2024-01-15 12:30:00');
        $this->createClosedOrder(10.00, '2024-01-15 14:00:00');

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/analytics/summary');

        $response->assertStatus(200);
        $peakHours = $response->json('data.peak_hours');
        $this->assertIsArray($peakHours);
    }

    public function test_top_products_returns_revenue_data(): void
    {
        $this->createOrderWithItems([
            ['product_id' => $this->productA->id, 'quantity' => 3, 'unit_price' => 8.50],
        ]);

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/analytics/top-products');

        $response->assertStatus(200);
        $this->assertEquals(25.50, $response->json('data.0.total_revenue'));
    }

    public function test_csv_export_with_date_filter(): void
    {
        $this->createClosedOrder(10.00, '2024-01-15 12:00:00');
        $this->createClosedOrder(20.00, '2024-01-20 12:00:00');

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/analytics/export/csv?start_date=2024-01-16');

        $response->assertStatus(200);

        $csvContent = $response->content();
        $this->assertStringNotContainsString('10.00', $csvContent);
        $this->assertStringContainsString('20.00', $csvContent);
    }

    public function test_sql_aggregation_uses_database_functions(): void
    {
        $this->createClosedOrder(10.00, '2024-01-15 12:00:00');
        $this->createClosedOrder(20.00, '2024-01-15 13:00:00');
        $this->createClosedOrder(30.00, '2024-01-15 14:00:00');

        $directSum = DB::table('orders')
            ->where('restaurant_id', $this->restaurantA->id)
            ->where('status', 'closed')
            ->sum('total_price');

        $directCount = DB::table('orders')
            ->where('restaurant_id', $this->restaurantA->id)
            ->where('status', 'closed')
            ->count();

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/analytics/summary');

        $response->assertStatus(200);
        $this->assertEquals($directSum, $response->json('data.total_revenue'));
        $this->assertEquals($directCount, $response->json('data.total_orders'));
    }

    public function test_unauthenticated_user_cannot_access_analytics(): void
    {
        $response = $this->getJson('/api/v1/owner/analytics/summary');

        $response->assertStatus(401);
    }

    public function test_analytics_returns_date_range(): void
    {
        $this->createClosedOrder(10.00, '2024-01-15 12:00:00');
        $this->createClosedOrder(20.00, '2024-01-20 12:00:00');

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/analytics/summary');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayHasKey('date_range', $data);
        $this->assertArrayHasKey('start', $data['date_range']);
        $this->assertArrayHasKey('end', $data['date_range']);
    }
}
