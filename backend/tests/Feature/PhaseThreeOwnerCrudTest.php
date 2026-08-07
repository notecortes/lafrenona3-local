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

class PhaseThreeOwnerCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerA;
    private User $ownerB;
    private Restaurant $restaurantA;
    private Restaurant $restaurantB;

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
    }

    private function ownerAToken(): string
    {
        return $this->ownerA->createToken('test')->plainTextToken;
    }

    private function ownerBToken(): string
    {
        return $this->ownerB->createToken('test')->plainTextToken;
    }

    public function test_owner_can_create_category(): void
    {
        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/owner/categories', [
                'name' => ['en' => 'Starters', 'es' => 'Entrantes'],
                'sort_order' => 1,
                'is_active' => true,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name.en', 'Starters');
        $response->assertJsonPath('data.restaurant_id', $this->restaurantA->id);

        $this->assertDatabaseHas('categories', [
            'restaurant_id' => $this->restaurantA->id,
            'sort_order' => 1,
        ]);
    }

    public function test_owner_can_list_categories(): void
    {
        Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Starters'],
            'sort_order' => 1,
        ]);

        Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Mains'],
            'sort_order' => 2,
        ]);

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/categories');

        $response->assertStatus(200);
        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_owner_can_update_category(): void
    {
        $category = Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Old Name'],
            'sort_order' => 1,
        ]);

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/owner/categories/' . $category->id, [
                'name' => ['en' => 'New Name'],
                'sort_order' => 5,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name.en', 'New Name');
        $response->assertJsonPath('data.sort_order', 5);
    }

    public function test_owner_can_delete_category(): void
    {
        $category = Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'To Delete'],
        ]);

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/owner/categories/' . $category->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_category_validation_requires_name(): void
    {
        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/owner/categories', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_owner_can_create_product(): void
    {
        $category = Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Starters'],
        ]);

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/owner/products', [
                'category_id' => $category->id,
                'name' => ['en' => 'Bruschetta', 'es' => 'Bruschetta'],
                'description' => ['en' => 'Toasted bread', 'es' => 'Pan tostado'],
                'price' => 8.50,
                'weekend_price' => 9.50,
                'is_vegan' => true,
                'is_vegetarian' => true,
                'allergens' => ['gluten', 'dairy'],
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name.en', 'Bruschetta');
        $response->assertJsonPath('data.price', '8.50');
        $response->assertJsonPath('data.is_vegan', true);

        $this->assertDatabaseHas('products', [
            'restaurant_id' => $this->restaurantA->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_owner_can_list_products(): void
    {
        $category = Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Starters'],
        ]);

        Product::create([
            'restaurant_id' => $this->restaurantA->id,
            'category_id' => $category->id,
            'name' => ['en' => 'Product A'],
            'price' => 10.00,
        ]);

        Product::create([
            'restaurant_id' => $this->restaurantA->id,
            'category_id' => $category->id,
            'name' => ['en' => 'Product B'],
            'price' => 12.00,
        ]);

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/products');

        $response->assertStatus(200);
        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_owner_can_update_product(): void
    {
        $category = Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Starters'],
        ]);

        $product = Product::create([
            'restaurant_id' => $this->restaurantA->id,
            'category_id' => $category->id,
            'name' => ['en' => 'Old Product'],
            'price' => 10.00,
        ]);

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/owner/products/' . $product->id, [
                'name' => ['en' => 'New Product'],
                'price' => 15.00,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name.en', 'New Product');
        $response->assertJsonPath('data.price', '15.00');
    }

    public function test_owner_can_delete_product(): void
    {
        $category = Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Starters'],
        ]);

        $product = Product::create([
            'restaurant_id' => $this->restaurantA->id,
            'category_id' => $category->id,
            'name' => ['en' => 'To Delete'],
            'price' => 10.00,
        ]);

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/owner/products/' . $product->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_product_validation_requires_price(): void
    {
        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/owner/products', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['price', 'category_id', 'name']);
    }

    public function test_product_validation_requires_valid_price(): void
    {
        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/owner/products', [
                'category_id' => 1,
                'name' => ['en' => 'Test'],
                'price' => -1.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['price']);
    }

    public function test_owner_can_create_table(): void
    {
        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/owner/tables', [
                'number' => 'A-01',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.number', 'A-01');
        $response->assertJsonPath('data.status', 'free');
        $response->assertJsonPath('data.secret_token', null);

        $this->assertDatabaseHas('tables', [
            'restaurant_id' => $this->restaurantA->id,
            'number' => 'A-01',
        ]);

        $table = Table::where('restaurant_id', $this->restaurantA->id)
            ->where('number', 'A-01')
            ->first();
        $this->assertNotNull($table);
        $this->assertNotNull($table->secret_token);
    }

    public function test_owner_can_list_tables(): void
    {
        Table::create([
            'restaurant_id' => $this->restaurantA->id,
            'number' => 'A-01',
        ]);

        Table::create([
            'restaurant_id' => $this->restaurantA->id,
            'number' => 'A-02',
        ]);

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/tables');

        $response->assertStatus(200);
        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_owner_can_update_table(): void
    {
        $table = Table::create([
            'restaurant_id' => $this->restaurantA->id,
            'number' => 'A-01',
        ]);

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/owner/tables/' . $table->id, [
                'status' => 'occupied',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'occupied');
    }

    public function test_owner_can_delete_table(): void
    {
        $table = Table::create([
            'restaurant_id' => $this->restaurantA->id,
            'number' => 'A-01',
        ]);

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/owner/tables/' . $table->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('tables', ['id' => $table->id]);
    }

    public function test_table_number_must_be_unique(): void
    {
        Table::create([
            'restaurant_id' => $this->restaurantA->id,
            'number' => 'A-01',
        ]);

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/owner/tables', [
                'number' => 'A-01',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['number']);
    }

    public function test_owner_can_create_staff(): void
    {
        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/owner/staff', [
                'name' => 'Waiter One',
                'email' => 'waiter.a@example.test',
                'password' => 'secret123',
                'role' => 'waiter',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Waiter One');
        $response->assertJsonPath('data.role', 'waiter');

        $this->assertDatabaseHas('users', [
            'email' => 'waiter.a@example.test',
            'role' => 'waiter',
            'restaurant_id' => $this->restaurantA->id,
        ]);
    }

    public function test_owner_can_list_staff(): void
    {
        User::create([
            'name' => 'Waiter A',
            'email' => 'waiter.list@example.test',
            'password' => Hash::make('password123'),
            'role' => 'waiter',
            'restaurant_id' => $this->restaurantA->id,
        ]);

        User::create([
            'name' => 'Kitchen A',
            'email' => 'kitchen.list@example.test',
            'password' => Hash::make('password123'),
            'role' => 'kitchen',
            'restaurant_id' => $this->restaurantA->id,
        ]);

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/staff');

        $response->assertStatus(200);
        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_owner_can_update_staff(): void
    {
        $staff = User::create([
            'name' => 'Old Name',
            'email' => 'waiter.update@example.test',
            'password' => Hash::make('password123'),
            'role' => 'waiter',
            'restaurant_id' => $this->restaurantA->id,
        ]);

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/owner/staff/' . $staff->id, [
                'name' => 'New Name',
                'role' => 'kitchen',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'New Name');
        $response->assertJsonPath('data.role', 'kitchen');
    }

    public function test_owner_can_delete_staff(): void
    {
        $staff = User::create([
            'name' => 'To Delete',
            'email' => 'waiter.delete@example.test',
            'password' => Hash::make('password123'),
            'role' => 'waiter',
            'restaurant_id' => $this->restaurantA->id,
        ]);

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/owner/staff/' . $staff->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
    }

    public function test_staff_validation_requires_email_password_role(): void
    {
        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/owner/staff', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
    }

    public function test_staff_validation_requires_valid_role(): void
    {
        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/owner/staff', [
                'name' => 'Test',
                'email' => 'test@example.test',
                'password' => 'password123',
                'role' => 'invalid_role',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_staff_password_must_be_min_8_chars(): void
    {
        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/owner/staff', [
                'name' => 'Test',
                'email' => 'test@example.test',
                'password' => 'short',
                'role' => 'waiter',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_staff_email_must_be_unique(): void
    {
        User::create([
            'name' => 'Existing',
            'email' => 'unique@example.test',
            'password' => Hash::make('password123'),
            'role' => 'waiter',
            'restaurant_id' => $this->restaurantA->id,
        ]);

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/owner/staff', [
                'name' => 'Duplicate',
                'email' => 'unique@example.test',
                'password' => 'password123',
                'role' => 'waiter',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_owner_cannot_access_other_restaurant_categories(): void
    {
        Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'A Category'],
        ]);

        $tokenB = $this->ownerBToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->getJson('/api/v1/owner/categories');

        $response->assertStatus(200);
        $this->assertEquals(0, count($response->json('data')));
    }

    public function test_owner_cannot_access_other_restaurant_products(): void
    {
        $categoryA = Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Category A'],
        ]);

        Product::create([
            'restaurant_id' => $this->restaurantA->id,
            'category_id' => $categoryA->id,
            'name' => ['en' => 'Product A'],
            'price' => 10.00,
        ]);

        $tokenB = $this->ownerBToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->getJson('/api/v1/owner/products');

        $response->assertStatus(200);
        $this->assertEquals(0, count($response->json('data')));
    }

    public function test_owner_cannot_access_other_restaurant_tables(): void
    {
        Table::create([
            'restaurant_id' => $this->restaurantA->id,
            'number' => 'A-01',
        ]);

        $tokenB = $this->ownerBToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->getJson('/api/v1/owner/tables');

        $response->assertStatus(200);
        $this->assertEquals(0, count($response->json('data')));
    }

    public function test_owner_cannot_access_other_restaurant_staff(): void
    {
        User::create([
            'name' => 'Waiter A',
            'email' => 'waiter.a.crud@example.test',
            'password' => Hash::make('password123'),
            'role' => 'waiter',
            'restaurant_id' => $this->restaurantA->id,
        ]);

        $tokenB = $this->ownerBToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->getJson('/api/v1/owner/staff');

        $response->assertStatus(200);
        $this->assertEquals(0, count($response->json('data')));
    }

    public function test_unauthenticated_user_cannot_access_owner_routes(): void
    {
        $response = $this->getJson('/api/v1/owner/categories');

        $response->assertStatus(401);
    }

    public function test_category_resource_includes_all_fields(): void
    {
        $category = Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Test Category'],
            'sort_order' => 3,
            'is_active' => false,
        ]);

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/categories/' . $category->id);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('restaurant_id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('sort_order', $data);
        $this->assertArrayHasKey('is_active', $data);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('updated_at', $data);
    }

    public function test_product_resource_includes_all_fields(): void
    {
        $category = Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Category'],
        ]);

        $product = Product::create([
            'restaurant_id' => $this->restaurantA->id,
            'category_id' => $category->id,
            'name' => ['en' => 'Test Product'],
            'price' => 15.00,
            'weekend_price' => 18.00,
            'is_vegan' => true,
            'is_vegetarian' => false,
            'allergens' => ['gluten'],
        ]);

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/products/' . $product->id);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('restaurant_id', $data);
        $this->assertArrayHasKey('category_id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('price', $data);
        $this->assertArrayHasKey('weekend_price', $data);
        $this->assertArrayHasKey('is_vegan', $data);
        $this->assertArrayHasKey('is_vegetarian', $data);
        $this->assertArrayHasKey('allergens', $data);
    }

    public function test_table_resource_does_not_expose_secret_token(): void
    {
        $table = Table::create([
            'restaurant_id' => $this->restaurantA->id,
            'number' => 'A-01',
        ]);

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/tables/' . $table->id);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertArrayNotHasKey('secret_token', $data);
        $this->assertArrayNotHasKey('current_session_token', $data);

        $this->assertNotNull($table->fresh()->secret_token);
    }

    public function test_staff_resource_excludes_password(): void
    {
        $staff = User::create([
            'name' => 'Waiter',
            'email' => 'waiter.resource@example.test',
            'password' => Hash::make('password123'),
            'role' => 'waiter',
            'restaurant_id' => $this->restaurantA->id,
        ]);

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/staff/' . $staff->id);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('remember_token', $data);
    }

    public function test_products_endpoint_returns_categories_list(): void
    {
        Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Active Cat'],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Inactive Cat'],
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/products/categories');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
        $this->assertEquals('Active Cat', $response->json('data.0.name.en'));
    }

    public function test_full_crud_flow_for_categories(): void
    {
        $token = $this->ownerAToken();

        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/owner/categories', [
                'name' => ['en' => 'Full Flow Test'],
                'sort_order' => 10,
            ]);

        $createResponse->assertStatus(201);
        $categoryId = $createResponse->json('data.id');

        $getResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/categories/' . $categoryId);

        $getResponse->assertStatus(200);
        $this->assertEquals('Full Flow Test', $getResponse->json('data.name.en'));

        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/owner/categories/' . $categoryId, [
                'name' => ['en' => 'Updated Full Flow Test'],
                'sort_order' => 20,
            ]);

        $updateResponse->assertStatus(200);
        $this->assertEquals('Updated Full Flow Test', $updateResponse->json('data.name.en'));
        $this->assertEquals(20, $updateResponse->json('data.sort_order'));

        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/owner/categories/' . $categoryId);

        $deleteResponse->assertStatus(200);
    }

    public function test_full_crud_flow_for_products(): void
    {
        $category = Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Category'],
        ]);

        $token = $this->ownerAToken();

        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/owner/products', [
                'category_id' => $category->id,
                'name' => ['en' => 'Full Flow Product'],
                'price' => 20.00,
            ]);

        $createResponse->assertStatus(201);
        $productId = $createResponse->json('data.id');

        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/owner/products/' . $productId, [
                'name' => ['en' => 'Updated Full Flow Product'],
                'price' => 25.00,
            ]);

        $updateResponse->assertStatus(200);
        $this->assertEquals('Updated Full Flow Product', $updateResponse->json('data.name.en'));

        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/owner/products/' . $productId);

        $deleteResponse->assertStatus(200);
    }

    public function test_full_crud_flow_for_tables(): void
    {
        $token = $this->ownerAToken();

        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/owner/tables', [
                'number' => 'FLOW-01',
            ]);

        $createResponse->assertStatus(201);
        $tableId = $createResponse->json('data.id');

        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/owner/tables/' . $tableId, [
                'status' => 'occupied',
            ]);

        $updateResponse->assertStatus(200);
        $this->assertEquals('occupied', $updateResponse->json('data.status'));

        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/owner/tables/' . $tableId);

        $deleteResponse->assertStatus(200);
    }

    public function test_full_crud_flow_for_staff(): void
    {
        $token = $this->ownerAToken();

        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/owner/staff', [
                'name' => 'Full Flow Staff',
                'email' => 'flow.staff2@example.test',
                'password' => 'password123',
                'role' => 'waiter',
            ]);

        $createResponse->assertStatus(201);
        $staffId = $createResponse->json('data.id');

        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/owner/staff/' . $staffId, [
                'name' => 'Updated Full Flow Staff',
                'role' => 'kitchen',
            ]);

        $updateResponse->assertStatus(200);
        $this->assertEquals('Updated Full Flow Staff', $updateResponse->json('data.name'));

        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/owner/staff/' . $staffId);

        $deleteResponse->assertStatus(200);
    }
}
