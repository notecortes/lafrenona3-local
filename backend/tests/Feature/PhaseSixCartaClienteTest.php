<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseSixCartaClienteTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerA;
    private User $ownerB;
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
            'name' => ['en' => 'Starters', 'es' => 'Entrantes'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->categoryB = Category::create([
            'restaurant_id' => $this->restaurantB->id,
            'name' => ['en' => 'Starters', 'es' => 'Entrantes'],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->productA = Product::create([
            'restaurant_id' => $this->restaurantA->id,
            'category_id' => $this->categoryA->id,
            'name' => ['en' => 'Bruschetta', 'es' => 'Bruschetta'],
            'description' => ['en' => 'Toasted bread', 'es' => 'Pan tostado'],
            'price' => 8.50,
            'is_available' => true,
        ]);

        $this->productB = Product::create([
            'restaurant_id' => $this->restaurantB->id,
            'category_id' => $this->categoryB->id,
            'name' => ['en' => 'Bruschetta', 'es' => 'Bruschetta'],
            'description' => ['en' => 'Toasted bread', 'es' => 'Pan tostado'],
            'price' => 9.00,
            'is_available' => true,
        ]);

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

    public function test_client_can_view_menu_with_valid_restaurant_slug(): void
    {
        $response = $this->getJson('/api/v1/client/menu?restaurant=qa-a');

        $response->assertStatus(200);
        $response->assertJsonPath('restaurant.slug', 'qa-a');
        $response->assertJsonPath('restaurant.name', 'Restaurante QA A');
        $this->assertEquals(1, count($response->json('categories')));
        $this->assertEquals(1, count($response->json('products')));
    }

    public function test_client_menu_returns_multilingual_names(): void
    {
        $response = $this->getJson('/api/v1/client/menu?restaurant=qa-a');

        $response->assertStatus(200);
        $category = $response->json('categories')[0];
        $this->assertIsArray($category['name']);
        $this->assertEquals('Starters', $category['name']['en']);
        $this->assertEquals('Entrantes', $category['name']['es']);
    }

    public function test_client_menu_returns_product_with_allergens(): void
    {
        $productWithAllergens = Product::create([
            'restaurant_id' => $this->restaurantA->id,
            'category_id' => $this->categoryA->id,
            'name' => ['en' => 'Allergen Product'],
            'price' => 12.00,
            'is_available' => true,
            'allergens' => ['gluten', 'dairy'],
        ]);

        $response = $this->getJson('/api/v1/client/menu?restaurant=qa-a');

        $response->assertStatus(200);
        $products = $response->json('products');
        $allergenProduct = collect($products)->firstWhere('id', $productWithAllergens->id);
        $this->assertNotNull($allergenProduct);
        $this->assertEquals(['gluten', 'dairy'], $allergenProduct['allergens']);
    }

    public function test_client_menu_excludes_inactive_products(): void
    {
        $productUnavailable = Product::create([
            'restaurant_id' => $this->restaurantA->id,
            'category_id' => $this->categoryA->id,
            'name' => ['en' => 'Unavailable Product'],
            'price' => 5.00,
            'is_available' => false,
        ]);

        $response = $this->getJson('/api/v1/client/menu?restaurant=qa-a');

        $response->assertStatus(200);
        $productIds = collect($response->json('products'))->pluck('id');
        $this->assertFalse($productIds->contains($productUnavailable->id));
    }

    public function test_client_menu_excludes_inactive_categories(): void
    {
        $inactiveCategory = Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Inactive Category'],
            'is_active' => false,
        ]);

        Product::create([
            'restaurant_id' => $this->restaurantA->id,
            'category_id' => $inactiveCategory->id,
            'name' => ['en' => 'Product in inactive category'],
            'price' => 5.00,
            'is_available' => true,
        ]);

        $response = $this->getJson('/api/v1/client/menu?restaurant=qa-a');

        $response->assertStatus(200);
        $categoryIds = collect($response->json('categories'))->pluck('id');
        $this->assertFalse($categoryIds->contains($inactiveCategory->id));
    }

    public function test_suspended_restaurant_returns_403(): void
    {
        $suspendedRestaurant = Restaurant::create([
            'owner_id' => $this->ownerA->id,
            'name' => 'Suspended Restaurant',
            'slug' => 'suspended-qa',
            'status' => 'suspended',
        ]);

        $response = $this->getJson('/api/v1/client/menu?restaurant=suspended-qa');

        $response->assertStatus(403);
        $this->assertEquals('Restaurant is currently closed.', $response->json('message'));
    }

    public function test_invalid_restaurant_slug_returns_404(): void
    {
        $response = $this->getJson('/api/v1/client/menu?restaurant=nonexistent');

        $response->assertStatus(404);
        $this->assertEquals('Restaurant not found.', $response->json('message'));
    }

    public function test_no_restaurant_slug_returns_404(): void
    {
        $response = $this->getJson('/api/v1/client/menu');

        $response->assertStatus(404);
    }

    public function test_client_menu_with_table_session_token(): void
    {
        $response = $this->getJson('/api/v1/client/menu?restaurant=qa-a&token=' . $this->tableA->secret_token);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('session_token'));
        $this->assertEquals('A-01', $response->json('table_number'));

        $this->assertDatabaseHas('tables', [
            'id' => $this->tableA->id,
            'status' => 'occupied',
        ]);

        $this->assertNotNull($this->tableA->fresh()->seated_at);
    }

    public function test_client_menu_with_invalid_token_returns_menu_without_session(): void
    {
        $response = $this->getJson('/api/v1/client/menu?restaurant=qa-a&token=invalid-token-xyz');

        $response->assertStatus(200);
        $this->assertNull($response->json('session_token'));
        $this->assertNull($response->json('table_number'));

        $this->assertDatabaseHas('tables', [
            'id' => $this->tableA->id,
            'status' => 'free',
        ]);
    }

    public function test_client_menu_tenant_isolation(): void
    {
        $responseA = $this->getJson('/api/v1/client/menu?restaurant=qa-a');
        $responseB = $this->getJson('/api/v1/client/menu?restaurant=qa-b');

        $responseA->assertStatus(200);
        $responseB->assertStatus(200);

        $productsA = collect($responseA->json('products'));
        $productsB = collect($responseB->json('products'));

        $this->assertEquals(8.50, (float) $productsA->first()['price']);
        $this->assertEquals(9.00, (float) $productsB->first()['price']);

        $this->assertNotEquals(
            $responseA->json('restaurant.id'),
            $responseB->json('restaurant.id')
        );
    }

    public function test_table_session_token_cannot_be_used_for_another_restaurant(): void
    {
        $response = $this->getJson('/api/v1/client/menu?restaurant=qa-b&token=' . $this->tableA->secret_token);

        $response->assertStatus(200);
        $this->assertNull($response->json('session_token'));
        $this->assertNull($response->json('table_number'));

        $this->assertDatabaseHas('tables', [
            'id' => $this->tableA->id,
            'status' => 'free',
        ]);
    }

    public function test_menu_returns_empty_when_no_products(): void
    {
        $emptyRestaurant = Restaurant::create([
            'owner_id' => $this->ownerA->id,
            'name' => 'Empty Restaurant',
            'slug' => 'empty-qa',
            'status' => 'active',
        ]);

        Category::create([
            'restaurant_id' => $emptyRestaurant->id,
            'name' => ['en' => 'Empty Category'],
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/client/menu?restaurant=empty-qa');

        $response->assertStatus(200);
        $this->assertEquals(0, count($response->json('products')));
    }

    public function test_menu_response_contains_required_fields(): void
    {
        $response = $this->getJson('/api/v1/client/menu?restaurant=qa-a');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'restaurant' => ['id', 'name', 'slug'],
            'categories' => [['id', 'name', 'description', 'sort_order']],
            'products' => [['id', 'category_id', 'name', 'description', 'price', 'allergens', 'is_available']],
        ]);
    }

    public function test_menu_price_is_preserved_as_decimal(): void
    {
        $response = $this->getJson('/api/v1/client/menu?restaurant=qa-a');

        $response->assertStatus(200);
        $price = $response->json('products.0.price');
        $this->assertEquals(8.50, (float) $price);
    }

    public function test_menu_with_multiple_products_in_same_category(): void
    {
        Product::create([
            'restaurant_id' => $this->restaurantA->id,
            'category_id' => $this->categoryA->id,
            'name' => ['en' => 'Second Starter'],
            'price' => 10.00,
            'is_available' => true,
        ]);

        $response = $this->getJson('/api/v1/client/menu?restaurant=qa-a');

        $response->assertStatus(200);
        $this->assertEquals(2, count($response->json('products')));
    }

    public function test_menu_with_multiple_categories(): void
    {
        Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Mains', 'es' => 'Principales'],
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/client/menu?restaurant=qa-a');

        $response->assertStatus(200);
        $this->assertEquals(2, count($response->json('categories')));
    }

    public function test_table_session_generates_new_token_on_each_visit(): void
    {
        $firstToken = $this->tableA->secret_token;

        $this->getJson('/api/v1/client/menu?restaurant=qa-a&token=' . $firstToken);
        $firstSession = $this->tableA->fresh()->session_token;

        $this->getJson('/api/v1/client/menu?restaurant=qa-a&token=' . $firstToken);
        $secondSession = $this->tableA->fresh()->session_token;

        $this->assertNotNull($firstSession);
        $this->assertNotNull($secondSession);
        $this->assertNotEquals($firstSession, $secondSession);
    }

    public function test_menu_does_not_expose_internal_data(): void
    {
        $response = $this->getJson('/api/v1/client/menu?restaurant=qa-a');

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertArrayNotHasKey('password', $json);
        $this->assertArrayNotHasKey('token', $json);
        $this->assertArrayNotHasKey('secret', $json);
        $this->assertArrayNotHasKey('api_key', $json);
    }
}
