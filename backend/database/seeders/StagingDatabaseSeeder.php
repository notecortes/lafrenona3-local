<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\InventoryAdjustment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StagingDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('users')->truncate();
        DB::table('restaurants')->truncate();
        DB::table('categories')->truncate();
        DB::table('products')->truncate();
        DB::table('tables')->truncate();
        DB::table('orders')->truncate();
        DB::table('order_items')->truncate();
        DB::table('subscriptions')->truncate();
        DB::table('reservations')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->createSuperAdmin();
        $this->createRestaurants();
        $this->createMenuItems();
        $this->createTables();
        $this->createSampleOrders();
        $this->createSampleReservations();
        $this->createInventoryData();

        $this->command->info('Staging database seeded successfully!');
    }

    protected function createSuperAdmin(): void
    {
        User::create([
            'name' => 'Super Admin Staging',
            'email' => 'superadmin@staging.lafrenona3.test',
            'password' => Hash::make('SuperAdmin123!'),
            'role' => 'superadmin',
        ]);
    }

    protected function createRestaurants(): void
    {
        $restaurants = [
            [
                'name' => 'Restaurante Test A',
                'slug' => 'test-a',
                'status' => 'active',
            ],
            [
                'name' => 'Restaurante Test B',
                'slug' => 'test-b',
                'status' => 'active',
            ],
            [
                'name' => 'Restaurante Suspendido',
                'slug' => 'suspended-test',
                'status' => 'suspended',
            ],
        ];

        foreach ($restaurants as $index => $restaurantData) {
            $owner = User::create([
                'name' => "Owner Test {$index}",
                'email' => "owner{$index}@staging.lafrenona3.test",
                'password' => Hash::make('Owner123!'),
                'role' => 'owner',
            ]);

            $restaurant = Restaurant::create([
                'owner_id' => $owner->id,
                'name' => $restaurantData['name'],
                'slug' => $restaurantData['slug'],
                'status' => $restaurantData['status'],
            ]);

            $owner->update(['restaurant_id' => $restaurant->id]);

            // Create staff for each restaurant
            $roles = ['waiter', 'kitchen', 'bar'];
            foreach ($roles as $roleIndex => $role) {
                User::create([
                    'name' => "Staff {$role} {$index}",
                    'email' => "{$role}{$index}@staging.lafrenona3.test",
                    'password' => Hash::make('Staff123!'),
                    'role' => $role,
                    'restaurant_id' => $restaurant->id,
                ]);
            }

            // Create subscription
            $owner->subscription()->create([
                'restaurant_id' => $restaurant->id,
                'plan_name' => $index === 2 ? 'suspended' : 'premium',
                'status' => $index === 2 ? 'suspended' : 'active',
                'ends_at' => now()->addMonths(12),
            ]);
        }
    }

    protected function createMenuItems(): void
    {
        $menuData = [
            [
                'name' => ['en' => 'Starters', 'es' => 'Entrantes'],
                'products' => [
                    ['name' => ['en' => 'Bruschetta', 'es' => 'Bruschetta'], 'price' => 8.50, 'allergens' => ['gluten']],
                    ['name' => ['en' => 'Patatas Bravas', 'es' => 'Patatas Bravas'], 'price' => 6.00, 'allergens' => []],
                    ['name' => ['en' => 'Croquetas', 'es' => 'Croquetas'], 'price' => 9.50, 'allergens' => ['gluten', 'dairy']],
                ],
            ],
            [
                'name' => ['en' => 'Main Course', 'es' => 'Plato Principal'],
                'products' => [
                    ['name' => ['en' => 'Steak', 'es' => 'Bistec'], 'price' => 18.00, 'allergens' => []],
                    ['name' => ['en' => 'Fish', 'es' => 'Pescado'], 'price' => 16.50, 'allergens' => ['fish']],
                    ['name' => ['en' => 'Pasta', 'es' => 'Pasta'], 'price' => 12.00, 'allergens' => ['gluten']],
                ],
            ],
            [
                'name' => ['en' => 'Desserts', 'es' => 'Postres'],
                'products' => [
                    ['name' => ['en' => 'Tiramisu', 'es' => 'Tiramisu'], 'price' => 7.00, 'allergens' => ['dairy', 'gluten']],
                    ['name' => ['en' => 'Chocolate Cake', 'es' => 'Tarta de Chocolate'], 'price' => 7.50, 'allergens' => ['gluten', 'dairy', 'eggs']],
                ],
            ],
        ];

        // Restaurant IDs (1, 2, 3)
        foreach ($menuData as $index => $categoryData) {
            foreach ([1, 2] as $restaurantId) {
                $category = Category::create([
                    'restaurant_id' => $restaurantId,
                    'name' => $categoryData['name'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]);

                foreach ($categoryData['products'] as $productIndex => $productData) {
                    Product::create([
                        'restaurant_id' => $restaurantId,
                        'category_id' => $category->id,
                        'name' => $productData['name'],
                        'price' => $productData['price'],
                        'allergens' => $productData['allergens'],
                        'is_available' => true,
                        'is_active' => true,
                    ]);
                }
            }
        }
    }

    protected function createTables(): void
    {
        for ($restaurantId = 1; $restaurantId <= 3; $restaurantId++) {
            $tableNumbers = ['01', '02', '03', '04', '05'];
            foreach ($tableNumbers as $number) {
                Table::create([
                    'restaurant_id' => $restaurantId,
                    'number' => "A-{$number}",
                    'status' => 'free',
                    'capacity' => 4,
                ]);
            }
        }
    }

    protected function createSampleOrders(): void
    {
        $statuses = ['pending', 'closed'];
        $products = Product::where('is_available', true)->get();

        foreach ($products->take(10) as $product) {
            Order::create([
                'restaurant_id' => $product->restaurant_id,
                'table_id' => Table::where('restaurant_id', $product->restaurant_id)->first()->id,
                'session_token' => 'staging-session-' . uniqid(),
                'status' => $statuses[array_rand($statuses)],
                'total_price' => $product->price * 2,
            ]);
        }
    }

    protected function createSampleReservations(): void
    {
        $restaurants = Restaurant::where('status', 'active')->get();

        foreach ($restaurants as $restaurant) {
            Reservation::create([
                'restaurant_id' => $restaurant->id,
                'guest_name' => 'Test Guest',
                'guest_email' => 'guest' . $restaurant->id . '@staging.lafrenona3.test',
                'guest_phone' => '+34600000000',
                'party_size' => 4,
                'reservation_date' => now()->addDays(1),
                'reservation_time' => '20:00:00',
                'status' => 'pending',
                'notes' => 'Staging test reservation',
            ]);
        }
    }

    protected function createInventoryData(): void
    {
        $ingredients = [
            ['name' => 'Tomatoes', 'unit' => 'kg', 'quantity' => 50, 'min_quantity' => 10],
            ['name' => 'Olive Oil', 'unit' => 'liters', 'quantity' => 20, 'min_quantity' => 5],
            ['name' => 'Flour', 'unit' => 'kg', 'quantity' => 30, 'min_quantity' => 8],
            ['name' => 'Milk', 'unit' => 'liters', 'quantity' => 25, 'min_quantity' => 10],
        ];

        foreach ($ingredients as $ingredientData) {
            $ingredient = Ingredient::create($ingredientData);

            foreach ([1, 2] as $restaurantId) {
                InventoryAdjustment::create([
                    'restaurant_id' => $restaurantId,
                    'ingredient_id' => $ingredient->id,
                    'adjustment_type' => 'initial',
                    'quantity' => $ingredientData['quantity'],
                    'note' => 'Initial stock for staging',
                ]);
            }
        }
    }
}
