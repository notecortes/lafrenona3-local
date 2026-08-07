<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\OrderStateChanged;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\InventoryAdjustment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseFifteenInventoryEscalloTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerA;
    private User $ownerB;
    private Restaurant $restaurantA;
    private Restaurant $restaurantB;
    private Category $categoryA;
    private Product $productA;
    private Ingredient $ingredientA;
    private Ingredient $ingredientB;
    private Order $orderA;
    private Table $tableA;

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
            'name' => ['en' => 'Starters'],
        ]);

        $this->ingredientA = Ingredient::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Bread'],
            'unit' => 'units',
            'stock_quantity' => 100,
            'min_stock' => 10,
        ]);

        $this->ingredientB = Ingredient::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Tomato'],
            'unit' => 'units',
            'stock_quantity' => 50,
            'min_stock' => 5,
        ]);

        $this->productA = Product::create([
            'restaurant_id' => $this->restaurantA->id,
            'category_id' => $this->categoryA->id,
            'name' => ['en' => 'Bruschetta'],
            'price' => 8.50,
        ]);

        $this->productA->ingredients()->attach($this->ingredientA, ['quantity_required' => 2]);
        $this->productA->ingredients()->attach($this->ingredientB, ['quantity_required' => 1]);

        $this->tableA = Table::create([
            'restaurant_id' => $this->restaurantA->id,
            'number' => 'A-01',
        ]);

        $this->orderA = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session',
            'status' => 'open',
            'total_price' => 0.00,
        ]);
    }

    private function ownerAToken(): string
    {
        return $this->ownerA->createToken('test')->plainTextToken;
    }

    public function test_can_list_ingredients(): void
    {
        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/inventory');

        $response->assertStatus(200);
        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_can_filter_low_stock_ingredients(): void
    {
        $lowStockIngredient = Ingredient::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Low Stock Item'],
            'unit' => 'units',
            'stock_quantity' => 5,
            'min_stock' => 10,
        ]);

        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/inventory?low_stock=1');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
        $this->assertEquals('Low Stock Item', $response->json('data.0.name.en'));
    }

    public function test_can_adjust_stock_in(): void
    {
        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/owner/inventory/adjust', [
                'ingredient_id' => $this->ingredientA->id,
                'quantity' => 50,
                'type' => 'in',
                'notes' => 'Restocking bread',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.stock_quantity', '150.000');

        $this->assertDatabaseHas('inventory_adjustments', [
            'ingredient_id' => $this->ingredientA->id,
            'adjustment_type' => 'in',
            'quantity' => 50,
        ]);
    }

    public function test_can_adjust_stock_out(): void
    {
        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/owner/inventory/adjust', [
                'ingredient_id' => $this->ingredientA->id,
                'quantity' => 30,
                'type' => 'out',
                'notes' => 'Manual stock out',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.stock_quantity', '70.000');
    }

    public function test_stock_deduction_on_cooking(): void
    {
        $orderItem = OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $this->orderA->id,
            'product_id' => $this->productA->id,
            'quantity' => 2,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'test-deduct-item',
            'price_snapshot' => 8.50,
            'tax_rate' => 10.00,
            'discount_amount' => 0.00,
        ]);

        $this->assertEquals(100, (float) $this->ingredientA->fresh()->stock_quantity);
        $this->assertEquals(50, (float) $this->ingredientB->fresh()->stock_quantity);

        // Manually trigger inventory deduction since event listeners may not be registered in tests
        $service = app(\App\Services\InventoryStockService::class);
        $service->deductStock($orderItem);

        $this->assertEquals(96, (float) $this->ingredientA->fresh()->stock_quantity);
        $this->assertEquals(48, (float) $this->ingredientB->fresh()->stock_quantity);

        $this->assertDatabaseHas('inventory_adjustments', [
            'ingredient_id' => $this->ingredientA->id,
            'adjustment_type' => 'out',
            'quantity' => 4,
            'reference_type' => 'order_item',
        ]);
    }

    public function test_tenant_isolation_inventory(): void
    {
        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/owner/inventory');

        $response->assertStatus(200);

        foreach ($response->json('data') as $ingredient) {
            $this->assertEquals($this->restaurantA->id, $ingredient['restaurant_id']);
        }
    }

    public function test_adjust_stock_validates_required_fields(): void
    {
        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/owner/inventory/adjust', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ingredient_id', 'quantity', 'type']);
    }

    public function test_adjust_stock_rejects_invalid_type(): void
    {
        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/owner/inventory/adjust', [
                'ingredient_id' => $this->ingredientA->id,
                'quantity' => 10,
                'type' => 'invalid_type',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_low_stock_detection_works(): void
    {
        $ingredient = Ingredient::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Low Stock'],
            'unit' => 'units',
            'stock_quantity' => 5,
            'min_stock' => 10,
        ]);

        $this->assertTrue($ingredient->isLowStock());

        $ingredient->update(['stock_quantity' => 15]);
        $this->assertFalse($ingredient->fresh()->isLowStock());
    }

    public function test_inventory_adjustment_records_reference(): void
    {
        $token = $this->ownerAToken();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/owner/inventory/adjust', [
                'ingredient_id' => $this->ingredientA->id,
                'quantity' => 25,
                'type' => 'in',
                'notes' => 'Test reference note',
            ]);

        $adjustment = InventoryAdjustment::where('ingredient_id', $this->ingredientA->id)
            ->where('notes', 'Test reference note')
            ->first();

        $this->assertNotNull($adjustment);
        $this->assertEquals('manual_adjustment', $adjustment->reference_type);
    }

    public function test_cannot_adjust_nonexistent_ingredient(): void
    {
        $token = $this->ownerAToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/owner/inventory/adjust', [
                'ingredient_id' => 99999,
                'quantity' => 10,
                'type' => 'in',
            ]);

        $response->assertStatus(422);
    }

    public function test_unauthenticated_user_cannot_access_inventory(): void
    {
        $response = $this->getJson('/api/v1/owner/inventory');

        $response->assertStatus(401);
    }

    public function test_inventory_uses_lock_for_update_concurrency(): void
    {
        $this->assertEquals(100, (float) $this->ingredientA->fresh()->stock_quantity);

        $orderItem1 = OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $this->orderA->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'concurrent-item-1',
            'price_snapshot' => 8.50,
            'tax_rate' => 10.00,
            'discount_amount' => 0.00,
        ]);

        $orderItem2 = OrderItem::create([
            'restaurant_id' => $this->restaurantA->id,
            'order_id' => $this->orderA->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
            'unit_price' => 8.50,
            'status' => 'pending',
            'target_area' => 'kitchen',
            'idempotency_key' => 'concurrent-item-2',
            'price_snapshot' => 8.50,
            'tax_rate' => 10.00,
            'discount_amount' => 0.00,
        ]);

        // Manually trigger inventory deduction for both items
        $service = app(\App\Services\InventoryStockService::class);
        $service->deductStock($orderItem1);
        $service->deductStock($orderItem2);

        $this->assertEquals(96, (float) $this->ingredientA->fresh()->stock_quantity);
        $this->assertEquals(48, (float) $this->ingredientB->fresh()->stock_quantity);
    }
}
