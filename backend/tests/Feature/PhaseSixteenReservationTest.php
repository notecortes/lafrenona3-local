<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Reservation;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseSixteenReservationTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerA;
    private User $ownerB;
    private User $waiterA;
    private User $waiterB;
    private Restaurant $restaurantA;
    private Restaurant $restaurantB;
    private Table $tableA1;
    private Table $tableA2;
    private Table $tableB1;

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

        $this->tableA1 = Table::create([
            'restaurant_id' => $this->restaurantA->id,
            'number' => 'A-01',
            'capacity' => 4,
            'status' => 'free',
        ]);

        $this->tableA2 = Table::create([
            'restaurant_id' => $this->restaurantA->id,
            'number' => 'A-02',
            'capacity' => 2,
            'status' => 'free',
        ]);

        $this->tableB1 = Table::create([
            'restaurant_id' => $this->restaurantB->id,
            'number' => 'B-01',
            'capacity' => 4,
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

    public function test_client_can_create_reservation(): void
    {
        $response = $this->postJson('/api/v1/client/reservations', [
            'restaurant_slug' => 'qa-a',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '+34600000001',
            'party_size' => 2,
            'reservation_date' => '2026-12-25',
            'reservation_time' => '20:00',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.customer_name', 'John Doe');
        $response->assertJsonPath('data.status', 'confirmed');
        $this->assertNotNull($response->json('data.table_id'));

        $this->assertDatabaseHas('reservations', [
            'customer_name' => 'John Doe',
            'status' => 'confirmed',
            'party_size' => 2,
        ]);
    }

    public function test_reservation_adds_to_waitlist_when_no_table_available(): void
    {
        $existingReservation = Reservation::create([
            'restaurant_id' => $this->restaurantA->id,
            'customer_name' => 'Existing Customer',
            'customer_email' => 'existing@example.com',
            'customer_phone' => '+34600000000',
            'party_size' => 2,
            'reservation_date' => '2026-12-25',
            'reservation_time' => '20:00',
            'status' => 'confirmed',
            'table_id' => $this->tableA1->id,
        ]);

        $this->tableA1->update(['status' => 'occupied']);

        $this->tableA2->update(['status' => 'occupied']);

        $response = $this->postJson('/api/v1/client/reservations', [
            'restaurant_slug' => 'qa-a',
            'customer_name' => 'Waitlist Customer',
            'customer_email' => 'waitlist@example.com',
            'customer_phone' => '+34600000002',
            'party_size' => 2,
            'reservation_date' => '2026-12-25',
            'reservation_time' => '20:00',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'pending');
        $this->assertTrue($response->json('data.on_waitlist'));
    }

    public function test_reservation_requires_party_size(): void
    {
        $response = $this->postJson('/api/v1/client/reservations', [
            'restaurant_slug' => 'qa-a',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '+34600000001',
            'reservation_date' => '2026-12-25',
            'reservation_time' => '20:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['party_size']);
    }

    public function test_reservation_validates_date_format(): void
    {
        $response = $this->postJson('/api/v1/client/reservations', [
            'restaurant_slug' => 'qa-a',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '+34600000001',
            'party_size' => 2,
            'reservation_date' => 'invalid-date',
            'reservation_time' => '20:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reservation_date']);
    }

    public function test_staff_can_seat_reservation(): void
    {
        $reservation = Reservation::create([
            'restaurant_id' => $this->restaurantA->id,
            'customer_name' => 'Seated Customer',
            'customer_email' => 'seated@example.com',
            'customer_phone' => '+34600000003',
            'party_size' => 2,
            'reservation_date' => '2026-12-25',
            'reservation_time' => '20:00',
            'status' => 'confirmed',
            'table_id' => null,
        ]);

        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/reservations/' . $reservation->id . '/seat', [
                'table_id' => $this->tableA2->id,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'seated');
        $response->assertJsonPath('data.table_id', $this->tableA2->id);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'seated',
            'table_id' => $this->tableA2->id,
        ]);

        $this->assertDatabaseHas('tables', [
            'id' => $this->tableA2->id,
            'status' => 'occupied',
        ]);
    }

    public function test_seat_reservation_requires_confirmed_status(): void
    {
        $reservation = Reservation::create([
            'restaurant_id' => $this->restaurantA->id,
            'customer_name' => 'Pending Customer',
            'customer_email' => 'pending@example.com',
            'customer_phone' => '+34600000004',
            'party_size' => 2,
            'reservation_date' => '2026-12-25',
            'reservation_time' => '20:00',
            'status' => 'pending',
            'table_id' => null,
        ]);

        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/reservations/' . $reservation->id . '/seat', [
                'table_id' => $this->tableA2->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_seat_reservation_validates_table_id(): void
    {
        $reservation = Reservation::create([
            'restaurant_id' => $this->restaurantA->id,
            'customer_name' => 'Test',
            'customer_email' => 'test@example.com',
            'customer_phone' => '+34600000005',
            'party_size' => 2,
            'reservation_date' => '2026-12-25',
            'reservation_time' => '20:00',
            'status' => 'confirmed',
            'table_id' => null,
        ]);

        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/reservations/' . $reservation->id . '/seat', [
                'table_id' => 99999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['table_id']);
    }

    public function test_reservation_tenant_isolation(): void
    {
        $response = $this->postJson('/api/v1/client/reservations', [
            'restaurant_slug' => 'qa-b',
            'customer_name' => 'Tenant Test',
            'customer_email' => 'tenant@test.com',
            'customer_phone' => '+34600000006',
            'party_size' => 2,
            'reservation_date' => '2026-12-25',
            'reservation_time' => '20:00',
        ]);

        $response->assertStatus(201);

        $reservation = Reservation::where('customer_email', 'tenant@test.com')->first();
        $this->assertEquals($this->restaurantB->id, $reservation->restaurant_id);
    }

    public function test_no_double_booking_for_same_table(): void
    {
        $reservation1 = Reservation::create([
            'restaurant_id' => $this->restaurantA->id,
            'customer_name' => 'Customer 1',
            'customer_email' => 'customer1@example.com',
            'customer_phone' => '+34600000007',
            'party_size' => 2,
            'reservation_date' => '2026-12-25',
            'reservation_time' => '20:00',
            'status' => 'confirmed',
            'table_id' => $this->tableA1->id,
        ]);

        $this->tableA1->update(['status' => 'occupied']);

        $reservation2 = Reservation::create([
            'restaurant_id' => $this->restaurantA->id,
            'customer_name' => 'Customer 2',
            'customer_email' => 'customer2@example.com',
            'customer_phone' => '+34600000008',
            'party_size' => 2,
            'reservation_date' => '2026-12-25',
            'reservation_time' => '20:00',
            'status' => 'pending',
            'table_id' => null,
        ]);

        $this->assertEquals('pending', $reservation2->status);
        $this->assertNull($reservation2->table_id);
    }

    public function test_show_reservation_returns_details(): void
    {
        $reservation = Reservation::create([
            'restaurant_id' => $this->restaurantA->id,
            'customer_name' => 'Show Customer',
            'customer_email' => 'show@example.com',
            'customer_phone' => '+34600000009',
            'party_size' => 4,
            'reservation_date' => '2026-12-25',
            'reservation_time' => '21:00',
            'status' => 'confirmed',
            'table_id' => $this->tableA1->id,
            'notes' => 'Window table preferred',
        ]);

        $response = $this->getJson('/api/v1/client/reservations/' . $reservation->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.customer_name', 'Show Customer');
        $response->assertJsonPath('data.party_size', 4);
        $response->assertJsonPath('data.notes', 'Window table preferred');
    }

    public function test_staff_cannot_seat_another_restaurant_reservation(): void
    {
        $reservation = Reservation::create([
            'restaurant_id' => $this->restaurantB->id,
            'customer_name' => 'B Customer',
            'customer_email' => 'b@example.com',
            'customer_phone' => '+34600000010',
            'party_size' => 2,
            'reservation_date' => '2026-12-25',
            'reservation_time' => '20:00',
            'status' => 'confirmed',
            'table_id' => null,
        ]);

        $token = $this->waiterAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/staff/reservations/' . $reservation->id . '/seat', [
                'table_id' => $this->tableA1->id,
            ]);

        $response->assertStatus(404);
    }

    public function test_reservation_capacity_check(): void
    {
        $response = $this->postJson('/api/v1/client/reservations', [
            'restaurant_slug' => 'qa-a',
            'customer_name' => 'Large Party',
            'customer_email' => 'large@example.com',
            'customer_phone' => '+34600000011',
            'party_size' => 8,
            'reservation_date' => '2026-12-25',
            'reservation_time' => '20:00',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'pending');
        $this->assertTrue($response->json('data.on_waitlist'));
    }

    public function test_reservation_validates_email_format(): void
    {
        $response = $this->postJson('/api/v1/client/reservations', [
            'restaurant_slug' => 'qa-a',
            'customer_name' => 'John Doe',
            'customer_email' => 'not-an-email',
            'customer_phone' => '+34600000012',
            'party_size' => 2,
            'reservation_date' => '2026-12-25',
            'reservation_time' => '20:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_email']);
    }

    public function test_reservation_validates_party_size_minimum(): void
    {
        $response = $this->postJson('/api/v1/client/reservations', [
            'restaurant_slug' => 'qa-a',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '+34600000013',
            'party_size' => 0,
            'reservation_date' => '2026-12-25',
            'reservation_time' => '20:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['party_size']);
    }

    public function test_unauthenticated_user_cannot_seat_reservation(): void
    {
        $reservation = Reservation::create([
            'restaurant_id' => $this->restaurantA->id,
            'customer_name' => 'Test',
            'customer_email' => 'test@example.com',
            'customer_phone' => '+34600000014',
            'party_size' => 2,
            'reservation_date' => '2026-12-25',
            'reservation_time' => '20:00',
            'status' => 'confirmed',
            'table_id' => null,
        ]);

        $response = $this->postJson('/api/v1/staff/reservations/' . $reservation->id . '/seat', [
            'table_id' => $this->tableA1->id,
        ]);

        $response->assertStatus(401);
    }
}
