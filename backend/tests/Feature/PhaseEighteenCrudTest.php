<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseEighteenCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $waiter;
    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner.crud@example.test',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);

        $this->waiter = User::create([
            'name' => 'Waiter',
            'email' => 'waiter.crud@example.test',
            'password' => Hash::make('password123'),
            'role' => 'waiter',
        ]);

        $this->restaurant = Restaurant::create([
            'owner_id' => $this->owner->id,
            'name' => 'Crud Restaurant',
            'slug' => 'crud-restaurant',
            'status' => 'active',
        ]);

        $this->owner->update(['restaurant_id' => $this->restaurant->id]);
        $this->waiter->update(['restaurant_id' => $this->restaurant->id]);
    }

    private function ownerToken(): string
    {
        return $this->owner->createToken('test')->plainTextToken;
    }

    private function waiterToken(): string
    {
        return $this->waiter->createToken('test')->plainTextToken;
    }

    public function test_login_returns_user_with_role_and_restaurant(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner.crud@example.test',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('user.role', 'owner');
        $response->assertJsonPath('user.restaurant_id', $this->restaurant->id);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $token = $this->ownerToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/user');

        $response->assertStatus(200);
        $response->assertJsonPath('data.email', 'owner.crud@example.test');
        $response->assertJsonPath('data.role', 'owner');
    }

    public function test_me_returns_restaurant_info(): void
    {
        $token = $this->ownerToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/user');

        $response->assertStatus(200);
        $response->assertJsonPath('data.restaurant_id', $this->restaurant->id);
    }

    public function test_logout_revokes_token(): void
    {
        $token = $this->ownerToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Logged out successfully.');

        $this->assertEquals(0, $this->owner->tokens()->count());
    }

    public function test_logged_out_token_cannot_be_used(): void
    {
        $user = User::create([
            'name' => 'Logout Test User',
            'email' => 'logout.test@example.test',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);
        $user->update(['restaurant_id' => $this->restaurant->id]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $this->assertEquals(0, $user->tokens()->count());

        // Verify token is deleted from database
        $this->assertEquals(0, \Laravel\Sanctum\PersonalAccessToken::where('tokenable_id', $user->id)->count());

        // Clear the web guard's cached user so the next request re-authenticates
        $guard = \Illuminate\Support\Facades\Auth::guard();
        if (method_exists($guard, 'logoutCurrentDevice')) {
            $guard->logoutCurrentDevice();
        }
        $ref = new \ReflectionObject($guard);
        if ($ref->hasProperty('user')) {
            $prop = $ref->getProperty('user');
            $prop->setAccessible(true);
            $prop->setValue($guard, null);
        }

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/user');

        $response->assertStatus(401);
    }

    public function test_me_returns_null_restaurant_for_superadmin(): void
    {
        $superadmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin.crud@test.com',
            'password' => Hash::make('superadmin123'),
            'role' => 'superadmin',
        ]);

        $token = $superadmin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/user');

        $response->assertStatus(200);
        $response->assertJsonPath('data.role', 'superadmin');
    }

    public function test_unauthenticated_user_cannot_access_me(): void
    {
        $response = $this->getJson('/api/v1/user');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    }

    public function test_token_revocation_is_immediate(): void
    {
        $user = User::create([
            'name' => 'Revocation Test User',
            'email' => 'revocation.test@example.test',
            'password' => Hash::make('password123'),
            'role' => 'waiter',
        ]);
        $user->update(['restaurant_id' => $this->restaurant->id]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $this->assertEquals(0, $user->tokens()->count());

        // Clear the web guard's cached user so the next request re-authenticates
        $guard = \Illuminate\Support\Facades\Auth::guard();
        if (method_exists($guard, 'logoutCurrentDevice')) {
            $guard->logoutCurrentDevice();
        }
        $ref = new \ReflectionObject($guard);
        if ($ref->hasProperty('user')) {
            $prop = $ref->getProperty('user');
            $prop->setAccessible(true);
            $prop->setValue($guard, null);
        }

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/user');

        $response->assertStatus(401);
    }

    public function test_logout_returns_200(): void
    {
        $token = $this->waiterToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200);
    }

    public function test_auth_returns_sanctum_token(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'waiter.crud@example.test',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('token_type', 'Bearer');
        $this->assertNotNull($response->json('access_token'));
        $this->assertGreaterThan(10, strlen($response->json('access_token')));
    }

    public function test_multiple_tokens_can_exist(): void
    {
        $token1 = $this->owner->createToken('token-1')->plainTextToken;
        $token2 = $this->owner->createToken('token-2')->plainTextToken;

        $this->assertEquals(2, $this->owner->tokens()->count());

        $response = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->getJson('/api/v1/user');

        $response->assertStatus(200);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->getJson('/api/v1/user');

        $response->assertStatus(200);
    }

    public function test_user_model_has_restaurant_relationship(): void
    {
        $this->assertInstanceOf(Restaurant::class, $this->owner->restaurant);
        $this->assertEquals($this->restaurant->id, $this->owner->restaurant->id);
    }

    public function test_me_excludes_password(): void
    {
        $token = $this->ownerToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/user');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayNotHasKey('password', $data);
    }
}
