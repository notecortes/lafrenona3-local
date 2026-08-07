<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseOneArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_has_correct_migrations_and_foreign_keys_constraints(): void
    {
        $owner = User::create([
            'name' => 'Owner Test',
            'email' => 'owner@test.com',
            'password' => Hash::make('secret123'),
            'role' => 'owner',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'owner@test.com']);

        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }

    public function test_user_can_login_via_api_and_receives_sanctum_token(): void
    {
        User::create([
            'name' => 'Staff Test',
            'email' => 'staff@test.com',
            'password' => Hash::make('password123'),
            'role' => 'waiter',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'staff@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'user' => ['id', 'name', 'email', 'role', 'restaurant_id'],
            ]);
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('correct_password'),
            'role' => 'owner',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_rejects_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'anypassword',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_returns_same_error_for_wrong_password_and_nonexistent_email(): void
    {
        User::create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => Hash::make('correct_password'),
            'role' => 'owner',
        ]);

        $responseWrongPassword = $this->postJson('/api/v1/auth/login', [
            'email' => 'existing@example.com',
            'password' => 'wrong_password',
        ]);

        $responseNonExistent = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'anypassword',
        ]);

        $responseWrongPassword->assertStatus(422);
        $responseNonExistent->assertStatus(422);

        $wrongPasswordMessage = $responseWrongPassword->json('message');
        $nonExistentMessage = $responseNonExistent->json('message');

        $this->assertEquals($wrongPasswordMessage, $nonExistentMessage);
    }

    public function test_login_response_does_not_expose_password_or_token(): void
    {
        User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $user = $response->json('user');

        $this->assertArrayNotHasKey('password', $user);
        $this->assertArrayNotHasKey('remember_token', $user);
        $this->assertArrayNotHasKey('api_token', $user);
    }

    public function test_unauthenticated_user_cannot_access_user_endpoint(): void
    {
        $response = $this->getJson('/api/v1/user');

        $response->assertStatus(401);
    }

    public function test_user_can_access_user_endpoint_with_valid_token(): void
    {
        $owner = User::create([
            'name' => 'Token Owner',
            'email' => 'token.owner@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);

        $restaurant = \App\Models\Restaurant::create([
            'owner_id' => $owner->id,
            'name' => 'Test Restaurant',
            'slug' => 'test-token-user',
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Authenticated User',
            'email' => 'auth@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'restaurant_id' => $restaurant->id,
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/user');

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'auth@example.com')
            ->assertJsonPath('data.role', 'owner');
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_rejects_invalid_email_format(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'not-an-email',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_model_has_required_fields(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'fields@test.com',
            'password' => Hash::make('password123'),
            'role' => 'waiter',
        ]);

        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('fields@test.com', $user->email);
        $this->assertEquals('waiter', $user->role);
        $this->assertNull($user->restaurant_id);
    }

    public function test_user_password_is_hidden(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'hidden@test.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);

        $userArray = $user->toArray();

        $this->assertArrayNotHasKey('password', $userArray);
        $this->assertArrayNotHasKey('remember_token', $userArray);
    }

    public function test_subscriptions_table_exists_with_owner_id_fk(): void
    {
        $owner = User::create([
            'name' => 'Owner for Subscriptions',
            'email' => 'sub.owner@test.com',
            'password' => Hash::make('secret123'),
            'role' => 'owner',
        ]);

        \DB::table('subscriptions')->insert([
            'owner_id' => $owner->id,
            'plan_name' => 'basic',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('subscriptions', ['owner_id' => $owner->id]);
    }

    public function test_restaurants_table_exists_with_owner_id_fk(): void
    {
        $owner = User::create([
            'name' => 'Owner for Restaurants',
            'email' => 'rest.owner@test.com',
            'password' => Hash::make('secret123'),
            'role' => 'owner',
        ]);

        \DB::table('restaurants')->insert([
            'owner_id' => $owner->id,
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('restaurants', ['slug' => 'test-restaurant']);
    }

    public function test_restaurant_slug_must_be_unique(): void
    {
        $owner = User::create([
            'name' => 'Owner',
            'email' => 'unique@test.com',
            'password' => Hash::make('secret123'),
            'role' => 'owner',
        ]);

        \DB::table('restaurants')->insert([
            'owner_id' => $owner->id,
            'name' => 'Restaurant One',
            'slug' => 'unique-slug',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        \DB::table('restaurants')->insert([
            'owner_id' => $owner->id,
            'name' => 'Restaurant Two',
            'slug' => 'unique-slug',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_tenant_designs_table_exists_with_restaurant_id_fk(): void
    {
        $owner = User::create([
            'name' => 'Owner for Design',
            'email' => 'design.owner@test.com',
            'password' => Hash::make('secret123'),
            'role' => 'owner',
        ]);

        $restaurantId = \DB::table('restaurants')->insertGetId([
            'owner_id' => $owner->id,
            'name' => 'Design Restaurant',
            'slug' => 'design-restaurant',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('tenant_designs')->insert([
            'restaurant_id' => $restaurantId,
            'primary_color' => '#FF5733',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('tenant_designs', ['restaurant_id' => $restaurantId]);
    }

    public function test_users_restaurant_id_foreign_key(): void
    {
        $owner = User::create([
            'name' => 'Owner',
            'email' => 'fk.owner@test.com',
            'password' => Hash::make('secret123'),
            'role' => 'owner',
        ]);

        $restaurantId = \DB::table('restaurants')->insertGetId([
            'owner_id' => $owner->id,
            'name' => 'FK Restaurant',
            'slug' => 'fk-restaurant',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::create([
            'name' => 'Staff User',
            'email' => 'staff.fk@test.com',
            'password' => Hash::make('secret123'),
            'role' => 'waiter',
            'restaurant_id' => $restaurantId,
        ]);

        $this->assertDatabaseHas('users', ['email' => 'staff.fk@test.com', 'restaurant_id' => $restaurantId]);
    }
}
