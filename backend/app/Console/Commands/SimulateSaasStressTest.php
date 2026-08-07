<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SimulateSaasStressTest extends Command
{
    protected $signature = 'stress:test 
                            {--users=10 : Number of concurrent users to simulate}
                            {--duration=60 : Duration in seconds}
                            {--endpoint=all : Endpoint to test (all, menu, login, orders)}';

    protected $description = 'Simulate stress test scenarios for the SaaS application';

    public function handle(): int
    {
        $this->info('Starting stress test simulation...');
        $this->info("Users: {$this->option('users')}");
        $this->info("Duration: {$this->option('duration')}s");
        $this->info("Endpoint: {$this->option('endpoint')}");
        echo PHP_EOL;

        $startTime = microtime(true);
        $totalRequests = 0;
        $successfulRequests = 0;
        $failedRequests = 0;
        $responseTimes = [];

        $users = $this->option('users');
        $duration = $this->option('duration');
        $endpoint = $this->option('endpoint');

        // Create test data if needed
        $this->createTestData();

        // Run simulation
        $endTime = $startTime + $duration;

        while (microtime(true) < $endTime) {
            for ($i = 0; $i < $users; $i++) {
                $requestStart = microtime(true);
                
                try {
                    $result = $this->simulateRequest($endpoint);
                    $requestDuration = (microtime(true) - $requestStart) * 1000; // ms
                    
                    $totalRequests++;
                    $responseTimes[] = $requestDuration;

                    if ($result) {
                        $successfulRequests++;
                    } else {
                        $failedRequests++;
                    }
                } catch (\Throwable $e) {
                    $failedRequests++;
                    $this->warn("Request failed: {$e->getMessage()}");
                }
            }

            sleep(1);
        }

        $totalDuration = microtime(true) - $startTime;

        // Calculate statistics
        $avgResponseTime = empty($responseTimes) ? 0 : array_sum($responseTimes) / count($responseTimes);
        $maxResponseTime = empty($responseTimes) ? 0 : max($responseTimes);
        $minResponseTime = empty($responseTimes) ? 0 : min($responseTimes);
        sort($responseTimes);
        $p95Index = (int) floor(count($responseTimes) * 0.95);
        $p99Index = (int) floor(count($responseTimes) * 0.99);
        $p95ResponseTime = $responseTimes[$p95Index] ?? 0;
        $p99ResponseTime = $responseTimes[$p99Index] ?? 0;

        $successRate = $totalRequests > 0 ? ($successfulRequests / $totalRequests) * 100 : 0;
        $requestsPerSecond = $totalDuration > 0 ? $totalRequests / $totalDuration : 0;

        // Display results
        $this->info(PHP_EOL . '=== Stress Test Results ===');
        $this->info("Total Requests: {$totalRequests}");
        $this->info("Successful: {$successfulRequests}");
        $this->info("Failed: {$failedRequests}");
        $this->info("Success Rate: {$successRate}%");
        $this->info("Requests/Second: {$requestsPerSecond}");
        $this->info("Total Duration: {$totalDuration}s");
        echo PHP_EOL;
        $this->info('--- Response Times ---');
        $this->info("Average: {$avgResponseTime}ms");
        $this->info("Min: {$minResponseTime}ms");
        $this->info("Max: {$maxResponseTime}ms");
        $this->info("P95: {$p95ResponseTime}ms");
        $this->info("P99: {$p99ResponseTime}ms");

        // Determine pass/fail
        $pass = $successRate >= 95 && $p95ResponseTime < 1000;
        
        echo PHP_EOL;
        if ($pass) {
            $this->info('✓ Stress test PASSED');
            return Command::SUCCESS;
        } else {
            $this->warn('✗ Stress test FAILED');
            $this->warn("Success rate: {$successRate}% (minimum 95%)");
            $this->warn("P95 response time: {$p95ResponseTime}ms (maximum 1000ms)");
            return Command::FAILURE;
        }
    }

    protected function createTestData(): void
    {
        $this->info('Creating test data...');

        // Ensure at least one restaurant exists
        if (Restaurant::count() === 0) {
            $owner = User::create([
                'name' => 'Stress Test Owner',
                'email' => 'stress.owner@test.com',
                'password' => bcrypt('password123'),
                'role' => 'owner',
            ]);

            $restaurant = Restaurant::create([
                'owner_id' => $owner->id,
                'name' => 'Stress Test Restaurant',
                'slug' => 'stress-test',
                'status' => 'active',
            ]);

            $owner->update(['restaurant_id' => $restaurant->id]);

            $category = \App\Models\Category::create([
                'restaurant_id' => $restaurant->id,
                'name' => ['en' => 'Test Category'],
                'sort_order' => 1,
                'is_active' => true,
            ]);

            for ($i = 1; $i <= 20; $i++) {
                Product::create([
                    'restaurant_id' => $restaurant->id,
                    'category_id' => $category->id,
                    'name' => ['en' => "Product {$i}"],
                    'price' => 10.00 + $i,
                    'is_available' => true,
                    'is_active' => true,
                ]);
            }

            Table::create([
                'restaurant_id' => $restaurant->id,
                'number' => 'STRESS-01',
                'status' => 'free',
            ]);

            $this->info('Test data created successfully');
        }
    }

    protected function simulateRequest(string $endpoint): bool
    {
        switch ($endpoint) {
            case 'menu':
                return $this->simulateMenuRequest();
            case 'login':
                return $this->simulateLoginRequest();
            case 'orders':
                return $this->simulateOrderRequest();
            case 'all':
            default:
                $requests = [
                    'menu' => 0.5,
                    'login' => 0.2,
                    'orders' => 0.3,
                ];
                
                $random = mt_rand() / mt_getrandmax();
                $cumulative = 0;
                $selected = 'menu';
                
                foreach ($requests as $type => $probability) {
                    $cumulative += $probability;
                    if ($random <= $cumulative) {
                        $selected = $type;
                        break;
                    }
                }
                
                return $this->simulateRequest($selected);
        }
    }

    protected function simulateMenuRequest(): bool
    {
        try {
            $restaurant = Restaurant::inRandomOrder()->first();
            if (!$restaurant) {
                return false;
            }

            $response = \Http::get("{$this->getApiUrl()}/client/menu?restaurant={$restaurant->slug}");
            return $response->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function simulateLoginRequest(): bool
    {
        try {
            $user = User::where('role', 'owner')->inRandomOrder()->first();
            if (!$user) {
                return false;
            }

            $response = \Http::post("{$this->getApiUrl()}/auth/login", [
                'email' => $user->email,
                'password' => 'Owner123!',
            ]);

            return $response->successful() && $response->json('access_token') !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function simulateOrderRequest(): bool
    {
        try {
            $restaurant = Restaurant::inRandomOrder()->first();
            if (!$restaurant) {
                return false;
            }

            $table = Table::where('restaurant_id', $restaurant->id)->first();
            if (!$table) {
                return false;
            }

            $product = Product::where('restaurant_id', $restaurant->id)->inRandomOrder()->first();
            if (!$product) {
                return false;
            }

            $order = Order::create([
                'restaurant_id' => $restaurant->id,
                'table_id' => $table->id,
                'session_token' => Str::random(32),
                'status' => 'pending',
                'total_price' => $product->price,
                'idempotency_key' => (string) Str::uuid(),
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => $product->price,
                'total_price' => $product->price,
                'status' => 'pending',
            ]);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function getApiUrl(): string
    {
        return config('app.url', 'http://localhost:8000') . '/api/v1';
    }
}
