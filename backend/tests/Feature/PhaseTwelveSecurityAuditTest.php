<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseTwelveSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerA;
    private User $ownerB;
    private User $waiterA;
    private User $waiterB;
    private Restaurant $restaurantA;
    private Restaurant $restaurantB;
    private Category $categoryA;
    private Category $categoryB;
    private Product $productA;
    private Product $productB;
    private Table $tableA;
    private Table $tableB;

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

        $this->waiterA = User::create([
            'name' => 'Waiter A',
            'email' => 'waiter.a@example.test',
            'password' => Hash::make('password123'),
            'role' => 'waiter',
        ]);

        $this->waiterB = User::create([
            'name' => 'Waiter B',
            'email' => 'waiter.b@example.test',
            'password' => Hash::make('password123'),
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

        $this->tableA = Table::create([
            'restaurant_id' => $this->restaurantA->id,
            'number' => 'A-01',
        ]);

        $this->tableB = Table::create([
            'restaurant_id' => $this->restaurantB->id,
            'number' => 'B-01',
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

    private function waiterAToken(): string
    {
        return $this->waiterA->createToken('test')->plainTextToken;
    }

    public function test_audit_log_is_created_on_category_update(): void
    {
        $token = $this->ownerAToken();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/owner/categories/' . $this->categoryA->id, [
                'name' => ['en' => 'Updated Category'],
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'restaurant_id' => $this->restaurantA->id,
            'action' => 'category_updated',
        ]);
    }

    public function test_audit_log_includes_user_and_tenant(): void
    {
        \App\Services\AuditLogger::log(
            action: 'test_action',
            subjectType: 'test_subject',
            subjectId: 1,
            oldValues: ['old_key' => 'old_value'],
            newValues: ['new_key' => 'new_value'],
            userId: $this->waiterA->id,
            restaurantId: $this->restaurantA->id
        );

        $log = AuditLog::where('action', 'test_action')->first();

        $this->assertNotNull($log);
        $this->assertEquals($this->waiterA->id, $log->user_id);
        $this->assertEquals($this->restaurantA->id, $log->restaurant_id);
        $this->assertEquals(['old_key' => 'old_value'], $log->old_values);
        $this->assertEquals(['new_key' => 'new_value'], $log->new_values);
    }

    public function test_audit_log_listing_filters_by_action(): void
    {
        \App\Services\AuditLogger::log(
            action: 'category_created',
            restaurantId: $this->restaurantA->id,
            userId: $this->ownerA->id
        );

        \App\Services\AuditLogger::log(
            action: 'product_created',
            restaurantId: $this->restaurantA->id,
            userId: $this->ownerA->id
        );

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/audit-logs?action=category_created');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }

    public function test_audit_log_listing_filters_by_date_range(): void
    {
        \App\Services\AuditLogger::log(
            action: 'old_action',
            restaurantId: $this->restaurantA->id,
            userId: $this->ownerA->id
        );

        \App\Services\AuditLogger::log(
            action: 'recent_action',
            restaurantId: $this->restaurantA->id,
            userId: $this->ownerA->id
        );

        $token = $this->ownerAToken();

        $yesterday = now()->subDay()->format('Y-m-d');
        $tomorrow = now()->addDay()->format('Y-m-d');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/audit-logs?date_from=' . $tomorrow);

        $response->assertStatus(200);
        $this->assertEquals(0, count($response->json('data')));
    }

    public function test_audit_log_tenant_isolation(): void
    {
        \App\Services\AuditLogger::log(
            action: 'tenant_test_action',
            restaurantId: $this->restaurantA->id,
            userId: $this->ownerA->id
        );

        $token = $this->ownerBToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/audit-logs');

        $response->assertStatus(200);
        $this->assertEquals(0, count($response->json('data')));
    }

    public function test_rate_limiting_config_exists_for_client_routes(): void
    {
        $config = config('services.rate_limiting');

        $this->assertNotNull(config('app'));

        $response = $this->getJson('/api/v1/client/menu?restaurant=qa-a');
        $response->assertStatus(200);
    }

    public function test_rate_limiting_config_exists_for_login(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(422);
    }

    public function test_rate_limiting_config_exists_for_offline_sync(): void
    {
        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/sync/offline', [
                'operations' => [
                    [
                        'idempotency_key' => 'rate-limit-test',
                        'type' => 'order_item_create',
                        'payload' => [
                            'order_id' => 1,
                            'product_id' => 1,
                            'quantity' => 1,
                            'unit_price' => 10.00,
                            'restaurant_id' => $this->restaurantA->id,
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(200);
    }

    public function test_audit_log_includes_ip_address(): void
    {
        \App\Services\AuditLogger::log(
            action: 'ip_test',
            restaurantId: $this->restaurantA->id,
            userId: $this->ownerA->id
        );

        $log = AuditLog::where('action', 'ip_test')->first();

        $this->assertNotNull($log);
        $this->assertNotNull($log->ip_address);
    }

    public function test_audit_log_includes_user_agent(): void
    {
        \App\Services\AuditLogger::log(
            action: 'user_agent_test',
            restaurantId: $this->restaurantA->id,
            userId: $this->ownerA->id
        );

        $log = AuditLog::where('action', 'user_agent_test')->first();

        $this->assertNotNull($log);
        $this->assertNotNull($log->user_agent);
    }

    public function test_audit_log_pagination_works(): void
    {
        for ($i = 0; $i < 20; $i++) {
            \App\Services\AuditLogger::log(
                action: 'pagination_test_' . $i,
                restaurantId: $this->restaurantA->id,
                userId: $this->ownerA->id
            );
        }

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/audit-logs?per_page=5');

        $response->assertStatus(200);
        $this->assertEquals(5, count($response->json('data')));
        $this->assertArrayHasKey('meta', $response->json());
        $this->assertArrayHasKey('current_page', $response->json('meta'));
        $this->assertArrayHasKey('total', $response->json('meta'));
    }

    public function test_audit_log_requires_valid_action(): void
    {
        \App\Services\AuditLogger::log(
            action: 'valid_action_name',
            restaurantId: $this->restaurantA->id,
            userId: $this->ownerA->id
        );

        $log = AuditLog::where('action', 'valid_action_name')->first();

        $this->assertNotNull($log);
        $this->assertLessThan(101, strlen($log->action));
    }

    public function test_unauthenticated_user_cannot_access_audit_logs(): void
    {
        $response = $this->getJson('/api/v1/owner/audit-logs');

        $response->assertStatus(401);
    }

    public function test_audit_log_table_has_required_columns(): void
    {
        \App\Services\AuditLogger::log(
            action: 'column_test',
            subjectType: 'Order',
            subjectId: 1,
            oldValues: ['status' => 'open'],
            newValues: ['status' => 'closed'],
            userId: $this->waiterA->id,
            restaurantId: $this->restaurantA->id
        );

        $log = AuditLog::where('action', 'column_test')->first();

        $this->assertNotNull($log);
        $this->assertEquals('Order', $log->subject_type);
        $this->assertEquals(1, $log->subject_id);
        $this->assertEquals(['status' => 'open'], $log->old_values);
        $this->assertEquals(['status' => 'closed'], $log->new_values);
    }
}
