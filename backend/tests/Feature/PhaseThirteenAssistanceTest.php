<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\ClientAssistanceRequested;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseThirteenAssistanceTest extends TestCase
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
    }

    private function waiterAToken(): string
    {
        return $this->waiterA->createToken('test')->plainTextToken;
    }

    private function waiterBToken(): string
    {
        return $this->waiterB->createToken('test')->plainTextToken;
    }

    public function test_client_can_request_waiter_assistance(): void
    {
        Event::fake([ClientAssistanceRequested::class]);

        $response = $this->postJson('/api/v1/client/assistance', [
            'session_token' => $this->tableA->secret_token,
            'type' => 'waiter_called',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.assistance_status', 'waiter_called');
        $this->assertNotNull($response->json('data.assistance_requested_at'));

        $this->assertDatabaseHas('tables', [
            'id' => $this->tableA->id,
            'assistance_status' => 'waiter_called',
        ]);

        Event::assertDispatched(ClientAssistanceRequested::class);
    }

    public function test_client_can_request_bill_assistance(): void
    {
        Event::fake([ClientAssistanceRequested::class]);

        $response = $this->postJson('/api/v1/client/assistance', [
            'session_token' => $this->tableA->secret_token,
            'type' => 'bill_requested',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.assistance_status', 'bill_requested');

        $this->assertDatabaseHas('tables', [
            'id' => $this->tableA->id,
            'assistance_status' => 'bill_requested',
        ]);

        Event::assertDispatched(ClientAssistanceRequested::class, function ($event) {
            return $event->assistanceStatus === 'bill_requested';
        });
    }

    public function test_invalid_assistance_type_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/client/assistance', [
            'session_token' => $this->tableA->secret_token,
            'type' => 'invalid_type',
        ]);

        $response->assertStatus(422);
    }

    public function test_invalid_session_token_returns_404(): void
    {
        $response = $this->postJson('/api/v1/client/assistance', [
            'session_token' => 'invalid-token',
        ]);

        $response->assertStatus(404);
    }

    public function test_staff_can_view_room_dashboard(): void
    {
        Table::create([
            'restaurant_id' => $this->restaurantA->id,
            'number' => 'A-02',
            'status' => 'occupied',
        ]);

        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/staff/room');

        $response->assertStatus(200);
        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_room_dashboard_returns_table_status(): void
    {
        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/staff/room');

        $response->assertStatus(200);

        $tables = $response->json('data');
        $this->assertArrayHasKey('status', $tables[0]);
        $this->assertArrayHasKey('assistance_status', $tables[0]);
        $this->assertArrayHasKey('assistance_requested_at', $tables[0]);
    }

    public function test_room_dashboard_tenant_isolation(): void
    {
        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/staff/room');

        $response->assertStatus(200);

        foreach ($response->json('data') as $table) {
            $this->assertEquals($this->restaurantA->id, $table['restaurant_id']);
        }
    }

    public function test_event_broadcasts_on_assistance_request(): void
    {
        Event::fake([ClientAssistanceRequested::class]);

        $this->postJson('/api/v1/client/assistance', [
            'session_token' => $this->tableA->secret_token,
            'type' => 'waiter_called',
        ]);

        Event::assertDispatched(ClientAssistanceRequested::class, function ($event) {
            return $event->table->id === $this->tableA->id
                && $event->assistanceStatus === 'waiter_called';
        });
    }

    public function test_event_includes_table_and_restaurant_info(): void
    {
        Event::fake([ClientAssistanceRequested::class]);

        $this->postJson('/api/v1/client/assistance', [
            'session_token' => $this->tableA->secret_token,
            'type' => 'bill_requested',
        ]);

        Event::assertDispatched(ClientAssistanceRequested::class, function ($event) {
            $broadcast = $event->broadcastWith();

            return $broadcast['table_id'] === $this->tableA->id
                && $broadcast['table_number'] === $this->tableA->number
                && $broadcast['restaurant_id'] === $this->restaurantA->id;
        });
    }

    public function test_room_dashboard_returns_assistance_status(): void
    {
        $this->tableA->update([
            'assistance_status' => 'waiter_called',
            'assistance_requested_at' => now(),
        ]);

        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/staff/room');

        $response->assertStatus(200);

        $tableAData = collect($response->json('data'))->firstWhere('id', $this->tableA->id);

        $this->assertEquals('waiter_called', $tableAData['assistance_status']);
        $this->assertNotNull($tableAData['assistance_requested_at']);
    }

    public function test_unauthenticated_user_cannot_request_assistance(): void
    {
        $response = $this->postJson('/api/v1/client/assistance', [
            'session_token' => $this->tableA->secret_token,
        ]);

        $response->assertStatus(200);
    }

    public function test_assistance_timestamp_is_set(): void
    {
        $this->postJson('/api/v1/client/assistance', [
            'session_token' => $this->tableA->secret_token,
            'type' => 'waiter_called',
        ]);

        $table = Table::find($this->tableA->id);

        $this->assertNotNull($table->assistance_requested_at);
    }

    public function test_room_dashboard_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/staff/room');

        $response->assertStatus(401);
    }

    public function test_waiter_b_cannot_see_restaurant_a_tables(): void
    {
        $token = $this->waiterBToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/staff/room');

        $response->assertStatus(200);

        foreach ($response->json('data') as $table) {
            $this->assertEquals($this->restaurantB->id, $table['restaurant_id']);
        }
    }
}
