<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseNineSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
            'password' => Hash::make('superadmin123'),
            'role' => 'superadmin',
        ]);
    }

    private function superadminToken(): string
    {
        return $this->superadmin->createToken('test')->plainTextToken;
    }

    public function test_superadmin_can_list_restaurants(): void
    {
        $ownerA = User::create([
            'name' => 'Owner A',
            'email' => 'owner.a.list@test.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);

        $ownerB = User::create([
            'name' => 'Owner B',
            'email' => 'owner.b.list@test.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);

        Restaurant::create([
            'owner_id' => $ownerA->id,
            'name' => 'Restaurant A',
            'slug' => 'restaurant-a',
            'status' => 'active',
        ]);

        Restaurant::create([
            'owner_id' => $ownerB->id,
            'name' => 'Restaurant B',
            'slug' => 'restaurant-b',
            'status' => 'suspended',
        ]);

        $token = $this->superadminToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/superadmin/restaurants');

        $response->assertStatus(200);
        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_superadmin_can_create_restaurant_with_owner(): void
    {
        $token = $this->superadminToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/superadmin/restaurants', [
                'name' => 'New Restaurant',
                'slug' => 'new-restaurant',
                'owner_email' => 'new.owner@test.com',
                'owner_name' => 'New Owner',
                'owner_password' => 'password123',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'New Restaurant');
        $response->assertJsonPath('data.slug', 'new-restaurant');
        $response->assertJsonPath('data.status', 'active');
        $response->assertJsonPath('data.owner.name', 'New Owner');
        $response->assertJsonPath('data.owner.email', 'new.owner@test.com');

        $this->assertDatabaseHas('restaurants', [
            'slug' => 'new-restaurant',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'new.owner@test.com',
            'role' => 'owner',
        ]);
    }

    public function test_superadmin_can_show_restaurant_details(): void
    {
        $owner = User::create([
            'name' => 'Restaurant Owner',
            'email' => 'rest.owner@test.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);

        $restaurant = Restaurant::create([
            'owner_id' => $owner->id,
            'name' => 'Show Restaurant',
            'slug' => 'show-restaurant',
            'status' => 'active',
        ]);

        $token = $this->superadminToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/superadmin/restaurants/' . $restaurant->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Show Restaurant');
        $response->assertJsonPath('data.slug', 'show-restaurant');
        $response->assertJsonPath('data.owner.name', 'Restaurant Owner');
    }

    public function test_superadmin_can_suspend_restaurant(): void
    {
        $owner = User::create([
            'name' => 'Suspend Owner',
            'email' => 'suspend.owner@test.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);

        $restaurant = Restaurant::create([
            'owner_id' => $owner->id,
            'name' => 'Suspend Restaurant',
            'slug' => 'suspend-restaurant',
            'status' => 'active',
        ]);

        $token = $this->superadminToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/superadmin/restaurants/' . $restaurant->id . '/suspend');

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'suspended');

        $this->assertDatabaseHas('restaurants', [
            'id' => $restaurant->id,
            'status' => 'suspended',
        ]);
    }

    public function test_superadmin_can_activate_restaurant(): void
    {
        $owner = User::create([
            'name' => 'Activate Owner',
            'email' => 'activate.owner@test.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);

        $restaurant = Restaurant::create([
            'owner_id' => $owner->id,
            'name' => 'Activate Restaurant',
            'slug' => 'activate-restaurant',
            'status' => 'suspended',
        ]);

        $token = $this->superadminToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/superadmin/restaurants/' . $restaurant->id . '/activate');

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('restaurants', [
            'id' => $restaurant->id,
            'status' => 'active',
        ]);
    }

    public function test_superadmin_can_list_users(): void
    {
        User::create([
            'name' => 'User One',
            'email' => 'user.one@test.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);

        User::create([
            'name' => 'User Two',
            'email' => 'user.two@test.com',
            'password' => Hash::make('password123'),
            'role' => 'waiter',
        ]);

        $token = $this->superadminToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/superadmin/users');

        $response->assertStatus(200);
        $this->assertEquals(3, count($response->json('data')));
    }

    public function test_superadmin_can_create_user(): void
    {
        $token = $this->superadminToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/superadmin/users', [
                'name' => 'New User',
                'email' => 'new.user@test.com',
                'password' => 'password123',
                'role' => 'waiter',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'New User');
        $response->assertJsonPath('data.role', 'waiter');

        $this->assertDatabaseHas('users', [
            'email' => 'new.user@test.com',
            'role' => 'waiter',
        ]);
    }

    public function test_superadmin_can_suspend_user(): void
    {
        $user = User::create([
            'name' => 'Suspendable User',
            'email' => 'suspendable.user@test.com',
            'password' => Hash::make('password123'),
            'role' => 'waiter',
        ]);

        $token = $this->superadminToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/superadmin/users/' . $user->id . '/suspend');

        $response->assertStatus(200);
        $response->assertJsonPath('data.role', 'suspended');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'suspended',
        ]);
    }

    public function test_superadmin_cannot_suspend_superadmin(): void
    {
        $superadmin2 = User::create([
            'name' => 'Super Admin 2',
            'email' => 'superadmin2@test.com',
            'password' => Hash::make('superadmin123'),
            'role' => 'superadmin',
        ]);

        $token = $this->superadminToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/superadmin/users/' . $superadmin2->id . '/suspend');

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Cannot suspend a superadmin.');
    }

    public function test_non_superadmin_cannot_access_superadmin_routes(): void
    {
        $owner = User::create([
            'name' => 'Regular Owner',
            'email' => 'regular.owner@test.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);

        $token = $owner->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/superadmin/restaurants');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_superadmin_routes(): void
    {
        $response = $this->getJson('/api/v1/superadmin/restaurants');

        $response->assertStatus(401);
    }

    public function test_superadmin_bypasses_tenant_scope(): void
    {
        $ownerA = User::create([
            'name' => 'Owner A',
            'email' => 'owner.a.super@test.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);

        $ownerB = User::create([
            'name' => 'Owner B',
            'email' => 'owner.b.super@test.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);

        Restaurant::create([
            'owner_id' => $ownerA->id,
            'name' => 'Restaurant A',
            'slug' => 'super-restaurant-a',
            'status' => 'active',
        ]);

        Restaurant::create([
            'owner_id' => $ownerB->id,
            'name' => 'Restaurant B',
            'slug' => 'super-restaurant-b',
            'status' => 'active',
        ]);

        $token = $this->superadminToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/superadmin/restaurants');

        $response->assertStatus(200);
        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_create_restaurant_requires_required_fields(): void
    {
        $token = $this->superadminToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/superadmin/restaurants', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'slug', 'owner_email', 'owner_name', 'owner_password']);
    }

    public function test_create_user_requires_required_fields(): void
    {
        $token = $this->superadminToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/superadmin/users', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
    }

    public function test_create_user_rejects_invalid_role(): void
    {
        $token = $this->superadminToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/superadmin/users', [
                'name' => 'Test User',
                'email' => 'invalid.role@test.com',
                'password' => 'password123',
                'role' => 'invalid_role',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_create_restaurant_requires_unique_slug(): void
    {
        $owner = User::create([
            'name' => 'Existing Owner',
            'email' => 'existing.owner@test.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);

        Restaurant::create([
            'owner_id' => $owner->id,
            'name' => 'Existing Restaurant',
            'slug' => 'unique-slug',
            'status' => 'active',
        ]);

        $token = $this->superadminToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/superadmin/restaurants', [
                'name' => 'Duplicate Slug',
                'slug' => 'unique-slug',
                'owner_email' => 'new.owner@test.com',
                'owner_name' => 'New Owner',
                'owner_password' => 'password123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_create_user_requires_unique_email(): void
    {
        User::create([
            'name' => 'Existing User',
            'email' => 'unique.email@test.com',
            'password' => Hash::make('password123'),
            'role' => 'waiter',
        ]);

        $token = $this->superadminToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/superadmin/users', [
                'name' => 'Duplicate Email',
                'email' => 'unique.email@test.com',
                'password' => 'password123',
                'role' => 'waiter',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
