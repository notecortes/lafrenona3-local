<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Restaurant;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseTwoMultiTenancyTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerA;
    private User $ownerB;
    private User $superadmin;
    private Restaurant $restaurantA;
    private Restaurant $restaurantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
            'password' => Hash::make('superadmin123'),
            'role' => 'superadmin',
        ]);

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
    }

    private function authenticatedOwnerA(): string
    {
        return $this->ownerA->createToken('test')->plainTextToken;
    }

    private function authenticatedOwnerB(): string
    {
        return $this->ownerB->createToken('test')->plainTextToken;
    }

    public function test_tenant_scope_filters_categories_by_restaurant(): void
    {
        Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Category A'],
        ]);

        Category::create([
            'restaurant_id' => $this->restaurantB->id,
            'name' => ['en' => 'Category B'],
        ]);

        $tokenA = $this->authenticatedOwnerA();

        $responseA = $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->getJson('/api/v1/owner/restaurants');

        $responseA->assertStatus(200);
        $this->assertEquals($this->restaurantA->id, $responseA->json());
    }

    public function test_owner_cannot_access_other_restaurant_resources(): void
    {
        Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Category A'],
        ]);

        Category::create([
            'restaurant_id' => $this->restaurantB->id,
            'name' => ['en' => 'Category B'],
        ]);

        $categoriesB = Category::withoutGlobalScopes()
            ->where('restaurant_id', $this->restaurantB->id)
            ->get();

        $this->assertEquals(1, $categoriesB->count());
        $this->assertEquals($this->restaurantB->id, $categoriesB->first()->restaurant_id);

        $categoriesA = Category::withoutGlobalScopes()
            ->where('restaurant_id', $this->restaurantA->id)
            ->get();

        $this->assertEquals(1, $categoriesA->count());
        $this->assertEquals($this->restaurantA->id, $categoriesA->first()->restaurant_id);
    }

    public function test_superadmin_can_see_all_restaurants(): void
    {
        $allRestaurants = Restaurant::withoutGlobalScopes()->get();

        $this->assertEquals(2, $allRestaurants->count());

        $this->assertTrue($allRestaurants->contains('id', $this->restaurantA->id));
        $this->assertTrue($allRestaurants->contains('id', $this->restaurantB->id));
    }

    public function test_subscription_middleware_blocks_suspended_subscription(): void
    {
        Subscription::create([
            'owner_id' => $this->ownerA->id,
            'restaurant_id' => $this->restaurantA->id,
            'plan_name' => 'basic',
            'status' => 'past_due',
        ]);

        $token = $this->authenticatedOwnerA();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/restaurants');

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Subscription is not active.');
    }

    public function test_subscription_middleware_blocks_canceled_subscription(): void
    {
        Subscription::create([
            'owner_id' => $this->ownerA->id,
            'restaurant_id' => $this->restaurantA->id,
            'plan_name' => 'basic',
            'status' => 'canceled',
        ]);

        $token = $this->authenticatedOwnerA();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/restaurants');

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Subscription is not active.');
    }

    public function test_active_subscription_allows_access(): void
    {
        Subscription::create([
            'owner_id' => $this->ownerA->id,
            'restaurant_id' => $this->restaurantA->id,
            'plan_name' => 'pro',
            'status' => 'active',
            'ends_at' => now()->addMonths(12),
        ]);

        $token = $this->authenticatedOwnerA();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/restaurants');

        $response->assertStatus(200);
    }

    public function test_no_subscription_allows_access(): void
    {
        $token = $this->authenticatedOwnerA();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/restaurants');

        $response->assertStatus(200);
    }

    public function test_suspended_restaurant_blocks_access(): void
    {
        $this->restaurantA->update(['status' => 'suspended']);

        $token = $this->authenticatedOwnerA();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/restaurants');

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'This restaurant is suspended.');
    }

    public function test_superadmin_bypasses_subscription_check(): void
    {
        Subscription::create([
            'owner_id' => $this->ownerA->id,
            'restaurant_id' => $this->restaurantA->id,
            'plan_name' => 'basic',
            'status' => 'canceled',
        ]);

        $this->restaurantA->update(['status' => 'suspended']);

        $allRestaurants = Restaurant::withoutGlobalScopes()->get();

        $this->assertEquals(2, $allRestaurants->count());
    }

    public function test_owner_cannot_list_resources_from_another_tenant(): void
    {
        Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Category A1'],
            'sort_order' => 1,
        ]);

        Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Category A2'],
            'sort_order' => 2,
        ]);

        Category::create([
            'restaurant_id' => $this->restaurantB->id,
            'name' => ['en' => 'Category B1'],
            'sort_order' => 1,
        ]);

        $categoriesA = Category::withoutGlobalScopes()
            ->where('restaurant_id', $this->restaurantA->id)
            ->get();

        $categoriesB = Category::withoutGlobalScopes()
            ->where('restaurant_id', $this->restaurantB->id)
            ->get();

        $this->assertEquals(2, $categoriesA->count());
        $this->assertEquals(1, $categoriesB->count());

        foreach ($categoriesA as $category) {
            $this->assertEquals($this->restaurantA->id, $category->restaurant_id);
        }

        foreach ($categoriesB as $category) {
            $this->assertEquals($this->restaurantB->id, $category->restaurant_id);
        }
    }

    public function test_global_scope_applied_to_subscription_by_restaurant(): void
    {
        Subscription::create([
            'owner_id' => $this->ownerA->id,
            'restaurant_id' => $this->restaurantA->id,
            'plan_name' => 'pro',
            'status' => 'active',
        ]);

        Subscription::create([
            'owner_id' => $this->ownerB->id,
            'restaurant_id' => $this->restaurantB->id,
            'plan_name' => 'basic',
            'status' => 'active',
        ]);

        $subsA = Subscription::withoutGlobalScopes()
            ->where('restaurant_id', $this->restaurantA->id)
            ->get();

        $subsB = Subscription::withoutGlobalScopes()
            ->where('restaurant_id', $this->restaurantB->id)
            ->get();

        $this->assertEquals(1, $subsA->count());
        $this->assertEquals(1, $subsB->count());

        foreach ($subsA as $sub) {
            $this->assertEquals($this->restaurantA->id, $sub->restaurant_id);
        }

        foreach ($subsB as $sub) {
            $this->assertEquals($this->restaurantB->id, $sub->restaurant_id);
        }
    }

    public function test_tenant_context_resolves_correct_restaurant(): void
    {
        $context = app('tenant.context');
        $context->setTenant($this->restaurantA->id);

        $this->assertEquals($this->restaurantA->id, $context->get());

        $context->setTenant($this->restaurantB->id);
        $this->assertEquals($this->restaurantB->id, $context->get());

        $context->forget();
        $this->assertNull($context->get());
    }

    public function test_unauthenticated_user_cannot_access_tenant_routes(): void
    {
        $response = $this->getJson('/api/v1/owner/restaurants');

        $response->assertStatus(401);
    }

    public function test_restaurant_model_has_required_relationships(): void
    {
        $this->assertInstanceOf(User::class, $this->restaurantA->owner);
        $this->assertEquals($this->ownerA->id, $this->restaurantA->owner->id);

        $this->assertTrue($this->restaurantA->isActive());

        $this->restaurantA->update(['status' => 'suspended']);
        $this->assertFalse($this->restaurantA->isActive());
    }

    public function test_subscription_model_has_required_methods(): void
    {
        $activeSub = Subscription::create([
            'owner_id' => $this->ownerA->id,
            'restaurant_id' => $this->restaurantA->id,
            'plan_name' => 'pro',
            'status' => 'active',
            'ends_at' => now()->addMonths(6),
        ]);

        $this->assertTrue($activeSub->isActive());
        $this->assertFalse($activeSub->isSuspended());

        $pastDueSub = Subscription::create([
            'owner_id' => $this->ownerA->id,
            'restaurant_id' => $this->restaurantA->id,
            'plan_name' => 'basic',
            'status' => 'past_due',
        ]);

        $this->assertFalse($pastDueSub->isActive());
        $this->assertTrue($pastDueSub->isSuspended());

        $expiredSub = Subscription::create([
            'owner_id' => $this->ownerA->id,
            'restaurant_id' => $this->restaurantA->id,
            'plan_name' => 'basic',
            'status' => 'active',
            'ends_at' => now()->subDays(1),
        ]);

        $this->assertFalse($expiredSub->isActive());
    }

    public function test_category_model_applies_tenant_scope(): void
    {
        Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'A Cat'],
        ]);

        Category::create([
            'restaurant_id' => $this->restaurantB->id,
            'name' => ['en' => 'B Cat'],
        ]);

        $categoriesA = Category::withoutGlobalScopes()
            ->where('restaurant_id', $this->restaurantA->id)
            ->get();

        $categoriesB = Category::withoutGlobalScopes()
            ->where('restaurant_id', $this->restaurantB->id)
            ->get();

        foreach ($categoriesA as $cat) {
            $this->assertEquals($this->restaurantA->id, $cat->restaurant_id);
        }

        foreach ($categoriesB as $cat) {
            $this->assertEquals($this->restaurantB->id, $cat->restaurant_id);
        }
    }

    public function test_restaurant_id_column_exists_in_subscriptions(): void
    {
        Subscription::create([
            'owner_id' => $this->ownerA->id,
            'restaurant_id' => $this->restaurantA->id,
            'plan_name' => 'pro',
            'status' => 'active',
        ]);

        $sub = Subscription::withoutGlobalScopes()->where('owner_id', $this->ownerA->id)->first();
        $this->assertEquals($this->restaurantA->id, $sub->restaurant_id);
    }

    public function test_user_model_has_restaurant_relationship(): void
    {
        $this->assertInstanceOf(Restaurant::class, $this->ownerA->restaurant);
        $this->assertEquals($this->restaurantA->id, $this->ownerA->restaurant->id);

        $this->assertTrue($this->ownerA->isOwner());
        $this->assertFalse($this->ownerA->isSuperAdmin());

        $this->assertTrue($this->superadmin->isSuperAdmin());
        $this->assertFalse($this->superadmin->isOwner());
    }


    public function test_owner_cannot_modify_other_restaurant_subscription(): void
    {
        Subscription::create([
            'owner_id' => $this->ownerB->id,
            'restaurant_id' => $this->restaurantB->id,
            'plan_name' => 'pro',
            'status' => 'active',
        ]);

        $subsA = Subscription::withoutGlobalScopes()
            ->where('restaurant_id', $this->restaurantA->id)
            ->get();

        $this->assertEquals(0, $subsA->count());

        $subsB = Subscription::withoutGlobalScopes()
            ->where('restaurant_id', $this->restaurantB->id)
            ->get();

        $this->assertEquals(1, $subsB->count());
    }

    public function test_check_subscription_middleware_with_expired_subscription(): void
    {
        Subscription::create([
            'owner_id' => $this->ownerA->id,
            'restaurant_id' => $this->restaurantA->id,
            'plan_name' => 'basic',
            'status' => 'active',
            'ends_at' => now()->subDays(30),
        ]);

        $token = $this->authenticatedOwnerA();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/restaurants');

        $response->assertStatus(200);
    }

    public function test_tenant_scope_is_applied_by_default_on_category(): void
    {
        Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'A Cat'],
        ]);

        Category::create([
            'restaurant_id' => $this->restaurantB->id,
            'name' => ['en' => 'B Cat'],
        ]);

        $allWithoutScope = Category::withoutGlobalScopes()->get();
        $this->assertEquals(2, $allWithoutScope->count());

        $allWithScopeA = Category::where('restaurant_id', $this->restaurantA->id)->get();
        $this->assertEquals(1, $allWithScopeA->count());
        $this->assertEquals($this->restaurantA->id, $allWithScopeA->first()->restaurant_id);

        $allWithScopeB = Category::where('restaurant_id', $this->restaurantB->id)->get();
        $this->assertEquals(1, $allWithScopeB->count());
        $this->assertEquals($this->restaurantB->id, $allWithScopeB->first()->restaurant_id);
    }
}
