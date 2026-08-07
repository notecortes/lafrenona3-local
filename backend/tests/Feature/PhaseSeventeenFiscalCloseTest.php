<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Category;
use App\Models\FiscalRecord;
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

class PhaseSeventeenFiscalCloseTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerA;
    private User $ownerB;
    private User $waiterA;
    private User $waiterB;
    private Restaurant $restaurantA;
    private Restaurant $restaurantB;
    private Category $categoryA;
    private Product $productA;
    private Table $tableA;
    private Order $orderA;

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

    private function waiterAToken(): string
    {
        return $this->waiterA->createToken('test')->plainTextToken;
    }

    private function waiterBToken(): string
    {
        return $this->waiterB->createToken('test')->plainTextToken;
    }

    public function test_cash_session_can_be_opened(): void
    {
        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/cash-sessions', [
                'opening_amount' => 100.00,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.opening_amount', '100.00');
        $response->assertJsonPath('data.status', 'open');

        $this->assertDatabaseHas('cash_sessions', [
            'restaurant_id' => $this->restaurantA->id,
            'status' => 'open',
            'opening_amount' => 100.00,
        ]);
    }

    public function test_cash_session_can_be_closed(): void
    {
        $session = CashSession::create([
            'restaurant_id' => $this->restaurantA->id,
            'user_id' => $this->waiterA->id,
            'opened_at' => now()->subHours(8),
            'opening_amount' => 100.00,
            'expected_amount' => 0,
            'actual_amount' => null,
            'status' => 'open',
        ]);

        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/cash-sessions/' . $session->id . '/close', [
                'actual_amount' => 350.00,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'closed');
        $response->assertJsonPath('data.actual_amount', '350.00');
        $response->assertJsonPath('data.difference', '250.00');

        $this->assertDatabaseHas('cash_sessions', [
            'id' => $session->id,
            'status' => 'closed',
            'actual_amount' => 350.00,
            'expected_amount' => 100.00,
        ]);
    }

    public function test_cannot_open_two_open_sessions(): void
    {
        CashSession::create([
            'restaurant_id' => $this->restaurantA->id,
            'user_id' => $this->waiterA->id,
            'opened_at' => now(),
            'opening_amount' => 100.00,
            'status' => 'open',
        ]);

        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/cash-sessions', [
                'opening_amount' => 50.00,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'An open cash session already exists.');
    }

    public function test_fiscal_record_creation_with_hash_chaining(): void
    {
        $service = app(\App\Services\FiscalChainingService::class);

        $record1 = $service->createFiscalRecord($this->orderA);

        $this->assertNotNull($record1);
        $this->assertEquals(1, $record1->sequence_number);
        $this->assertNotNull($record1->hash);
        $this->assertEquals(64, strlen($record1->hash));

        $this->assertDatabaseHas('fiscal_records', [
            'sequence_number' => 1,
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $this->orderA->id,
            'total_amount' => 17.00,
        ]);
    }

    public function test_fiscal_hash_chain_is_valid(): void
    {
        $service = app(\App\Services\FiscalChainingService::class);

        $order2 = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session-2',
            'status' => 'closed',
            'total_price' => 25.50,
        ]);

        $record1 = $service->createFiscalRecord($this->orderA);
        $record2 = $service->createFiscalRecord($order2);

        $this->assertEquals(2, $record2->sequence_number);
        $this->assertEquals($record1->hash, $record2->prev_hash);

        $isValid = $service->verifyChain($this->restaurantA->id);
        $this->assertTrue($isValid);
    }

    public function test_fiscal_chain_detection_of_tampering(): void
    {
        $service = app(\App\Services\FiscalChainingService::class);

        $service->createFiscalRecord($this->orderA);

        $record = FiscalRecord::where('restaurant_id', $this->restaurantA->id)->first();
        $record->update(['total_amount' => 999.99]);

        $isValid = $service->verifyChain($this->restaurantA->id);
        $this->assertFalse($isValid);
    }

    public function test_cash_session_tenant_isolation(): void
    {
        $token = $this->waiterAToken();

        CashSession::create([
            'restaurant_id' => $this->restaurantA->id,
            'user_id' => $this->waiterA->id,
            'opened_at' => now(),
            'opening_amount' => 100.00,
            'status' => 'open',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/staff/cash-sessions');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }

    public function test_cannot_close_another_restaurant_session(): void
    {
        $session = CashSession::create([
            'restaurant_id' => $this->restaurantB->id,
            'user_id' => $this->waiterB->id,
            'opened_at' => now(),
            'opening_amount' => 100.00,
            'status' => 'open',
        ]);

        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/cash-sessions/' . $session->id . '/close', [
                'actual_amount' => 200.00,
            ]);

        $response->assertStatus(404);
    }

    public function test_fiscal_records_are_append_only(): void
    {
        $service = app(\App\Services\FiscalChainingService::class);
        $service->createFiscalRecord($this->orderA);

        $record = FiscalRecord::where('restaurant_id', $this->restaurantA->id)->first();
        $recordId = $record->id;

        $this->assertDatabaseHas('fiscal_records', [
            'id' => $recordId,
            'sequence_number' => 1,
        ]);

        $fiscalRecordsCount = FiscalRecord::where('restaurant_id', $this->restaurantA->id)->count();
        $this->assertEquals(1, $fiscalRecordsCount);
    }

    public function test_cash_session_listing_returns_data(): void
    {
        CashSession::create([
            'restaurant_id' => $this->restaurantA->id,
            'user_id' => $this->waiterA->id,
            'opened_at' => now()->subHours(8),
            'closing_amount' => 100.00,
            'status' => 'closed',
        ]);

        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/staff/cash-sessions');

        $response->assertStatus(200);
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_fiscal_invoice_listing_returns_data(): void
    {
        $service = app(\App\Services\FiscalChainingService::class);
        $service->createFiscalRecord($this->orderA);

        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/staff/fiscal-records');

        $response->assertStatus(200);
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_fiscal_record_has_required_fields(): void
    {
        $service = app(\App\Services\FiscalChainingService::class);
        $record = $service->createFiscalRecord($this->orderA);

        $this->assertNotNull($record->hash);
        $this->assertNotNull($record->prev_hash);
        $this->assertEquals('EUR', $record->currency);
        $this->assertEquals('issued', $record->status);
        $this->assertGreaterThan(0, $record->sequence_number);
    }

    public function test_unauthenticated_user_cannot_access_cash_sessions(): void
    {
        $response = $this->getJson('/api/v1/staff/cash-sessions');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_fiscal_records(): void
    {
        $response = $this->getJson('/api/v1/staff/fiscal-records');

        $response->assertStatus(401);
    }

    public function test_close_session_validates_required_fields(): void
    {
        $session = CashSession::create([
            'restaurant_id' => $this->restaurantA->id,
            'user_id' => $this->waiterA->id,
            'opened_at' => now(),
            'opening_amount' => 100.00,
            'status' => 'open',
        ]);

        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/cash-sessions/' . $session->id . '/close', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['actual_amount']);
    }

    public function test_close_session_requires_open_status(): void
    {
        $session = CashSession::create([
            'restaurant_id' => $this->restaurantA->id,
            'user_id' => $this->waiterA->id,
            'opened_at' => now()->subHours(8),
            'closed_at' => now(),
            'opening_amount' => 100.00,
            'actual_amount' => 300.00,
            'status' => 'closed',
        ]);

        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/cash-sessions/' . $session->id . '/close', [
                'actual_amount' => 400.00,
            ]);

        $response->assertStatus(422);
    }
}
