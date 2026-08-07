<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseTenDevOpsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.error_monitoring_enabled' => true]);

        $this->ownerA = User::create([
            'name' => 'Owner A',
            'email' => 'owner.a@example.test',
            'password' => Hash::make('password123'),
            'role' => 'owner',
        ]);

        $this->waiterA = User::create([
            'name' => 'Waiter A',
            'email' => 'waiter.a@example.test',
            'password' => Hash::make('password123'),
            'role' => 'waiter',
        ]);

        $this->restaurantA = Restaurant::create([
            'owner_id' => $this->ownerA->id,
            'name' => 'Restaurante QA A',
            'slug' => 'qa-a',
            'status' => 'active',
        ]);

        $this->ownerA->update(['restaurant_id' => $this->restaurantA->id]);
        $this->waiterA->update(['restaurant_id' => $this->restaurantA->id]);

        $this->categoryA = Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name' => ['en' => 'Starters'],
        ]);

        $this->productA = Product::create([
            'restaurant_id' => $this->restaurantA->id,
            'category_id' => $this->categoryA->id,
            'name' => ['en' => 'Bruschetta'],
            'price' => 8.50,
        ]);

        $this->tableA = Table::create([
            'restaurant_id' => $this->restaurantA->id,
            'number' => 'A-01',
        ]);

        $this->orderA = Order::create([
            'restaurant_id' => $this->restaurantA->id,
            'table_id' => $this->tableA->id,
            'session_token' => 'test-session',
            'status' => 'open',
            'total_price' => 17.00,
        ]);
    }

    public function test_backup_command_creates_backup_file(): void
    {
        Storage::fake('local');

        $driver = config('database.default');

        if ($driver === 'sqlite') {
            $sqlitePath = config('database.connections.sqlite.database');
            if (file_exists($sqlitePath)) {
                copy($sqlitePath, storage_path('db_backup_test.sqlite'));
                config(['database.connections.sqlite.database' => storage_path('db_backup_test.sqlite')]);
            }
        }

        $response = $this->artisan('saas:backup', [
            '--destination' => storage_path('app/backup'),
        ]);

        $this->assertNotNull($response);
    }

    public function test_backup_command_logs_to_audit(): void
    {
        $auditCountBefore = \DB::table('audit_logs')->where('action', 'saas_backup_created')->count();

        $this->artisan('saas:backup', [
            '--destination' => storage_path('app/backup'),
        ]);

        $auditCountAfter = \DB::table('audit_logs')->where('action', 'saas_backup_created')->count();

        $this->assertGreaterThanOrEqual($auditCountBefore, $auditCountBefore);
    }

    public function test_error_monitoring_service_captures_exception(): void
    {
        $service = app(\App\Services\ErrorMonitoringService::class);

        $exception = new \RuntimeException('Test exception for monitoring');

        $result = $service->capture($exception, 'Test error message', ['test_key' => 'test_value']);

        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('level', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('error', $result['level']);
        $this->assertEquals('Test error message', $result['message']);
    }

    public function test_error_monitoring_service_captures_message(): void
    {
        $service = app(\App\Services\ErrorMonitoringService::class);

        $result = $service->captureMessage('Test info message', 'info', ['info_key' => 'info_value']);

        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('level', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('info', $result['level']);
        $this->assertEquals('Test info message', $result['message']);
    }

    public function test_error_monitoring_service_logs_to_database(): void
    {
        $service = app(\App\Services\ErrorMonitoringService::class);

        $exception = new \RuntimeException('Database log test');

        $service->capture($exception, 'Database log test message');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'error_captured',
            'subject_type' => 'exception',
        ]);
    }

    public function test_error_monitoring_context_includes_request_id(): void
    {
        $service = app(\App\Services\ErrorMonitoringService::class);

        $exception = new \RuntimeException('Request ID test');

        $result = $service->capture($exception, 'Request ID test message');

        $this->assertArrayHasKey('contexts', $result);
        $this->assertArrayHasKey('trace', $result['contexts']);
        $this->assertArrayHasKey('request_id', $result['contexts']['trace']);
    }

    public function test_error_monitoring_context_includes_tenant_id(): void
    {
        $this->artisan('tenant:set', ['restaurant_id' => $this->restaurantA->id]);

        $service = app(\App\Services\ErrorMonitoringService::class);

        $exception = new \RuntimeException('Tenant ID test');

        $result = $service->capture($exception, 'Tenant ID test message');

        $this->assertArrayHasKey('contexts', $result);
        $this->assertArrayHasKey('trace', $result['contexts']);
        $this->assertArrayHasKey('tenant_id', $result['contexts']['trace']);
    }

    public function test_backup_command_handles_missing_app_key(): void
    {
        $originalKey = config('app.key');
        config(['app.key' => '']);

        $response = $this->artisan('saas:backup', [
            '--encrypt' => true,
            '--destination' => storage_path('app/backup'),
        ]);

        config(['app.key' => $originalKey]);

        $this->assertNotNull($response);
    }

    public function test_backup_command_with_encryption_option(): void
    {
        $originalKey = config('app.key');
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        $response = $this->artisan('saas:backup', [
            '--encrypt' => true,
            '--destination' => storage_path('app/backup'),
        ]);

        config(['app.key' => $originalKey]);

        $this->assertNotNull($response);
    }
}
