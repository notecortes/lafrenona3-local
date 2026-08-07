<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Client\ClientMenuController;
use App\Http\Controllers\Api\Client\ClientOrdersController;
use App\Http\Controllers\Api\Client\ClientPaymentController;
use App\Http\Controllers\Api\Client\ClientAssistanceController;
use App\Http\Controllers\Api\Client\ClientReservationController;
use App\Http\Controllers\Api\Owner\AnalyticsController;
use App\Http\Controllers\Api\Owner\AuditLogsController;
use App\Http\Controllers\Api\Owner\CategoriesController;
use App\Http\Controllers\Api\Owner\InventoryController;
use App\Http\Controllers\Api\Owner\ProductsController;
use App\Http\Controllers\Api\Owner\StaffController;
use App\Http\Controllers\Api\Owner\TablesController;
use App\Http\Controllers\Api\Staff\CashSessionController;
use App\Http\Controllers\Api\Staff\FiscalInvoiceController;
use App\Http\Controllers\Api\Staff\OrderItemsController;
use App\Http\Controllers\Api\Staff\StaffBillingController;
use App\Http\Controllers\Api\Staff\StaffRoomController;
use App\Http\Controllers\Api\Staff\StaffReservationController;
use App\Http\Controllers\Api\Staff\SyncOfflineController;
use App\Http\Controllers\Api\SuperAdmin\TenantManagementController;
use App\Http\Controllers\Api\Webhooks\StripeWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:auth_login')->post('/v1/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->post('/v1/auth/logout', [AuthController::class, 'logout']);

Route::middleware(['auth:sanctum', 'tenant.context'])->get('/v1/user', [AuthController::class, 'me']);

// Public client routes
Route::middleware('throttle:client_routes')->group(function () {
    Route::get('/v1/client/menu', [ClientMenuController::class, 'show']);
    Route::post('/v1/client/orders', [ClientOrdersController::class, 'store']);
    Route::post('/v1/client/orders/{order}/items', [ClientOrdersController::class, 'appendItems']);
    Route::post('/v1/client/orders/{order}/close', [ClientOrdersController::class, 'closeOrder']);
    Route::post('/v1/client/assistance', [ClientAssistanceController::class, 'request']);
    Route::post('/v1/client/payments/initiate', [ClientPaymentController::class, 'initiate']);
    Route::post('/v1/client/reservations', [ClientReservationController::class, 'store']);
    Route::get('/v1/client/reservations/{reservation}', [ClientReservationController::class, 'show']);
});

// Staff routes
Route::middleware(['auth:sanctum', 'tenant.context', 'check.owner.restaurant', 'check.subscription'])->prefix('/v1/staff')->group(function () {
    Route::get('/order-items/pending', [OrderItemsController::class, 'pendingItems']);
    Route::put('/order-items/{orderItem}/status', [OrderItemsController::class, 'updateStatus']);
    Route::put('/order-items/bulk', [OrderItemsController::class, 'bulkUpdate']);
    Route::post('/orders/{order}/close', [StaffBillingController::class, 'closeOrder']);
    Route::get('/room', [StaffRoomController::class, 'index']);
    Route::post('/sync/offline', [SyncOfflineController::class, 'sync']);
    Route::post('/reservations/{reservation}/seat', [StaffReservationController::class, 'seat']);
    Route::get('/cash-sessions', [CashSessionController::class, 'index']);
    Route::post('/cash-sessions', [CashSessionController::class, 'store']);
    Route::post('/cash-sessions/{cashSession}/close', [CashSessionController::class, 'close']);
    Route::get('/fiscal-records', [FiscalInvoiceController::class, 'index']);
});

// Owner routes
Route::middleware(['auth:sanctum', 'tenant.context', 'check.owner.restaurant', 'check.subscription'])->prefix('/v1/owner')->group(function () {
    Route::get('/restaurants', function (Request $request) {
        return $request->user()->restaurant_id;
    });

    Route::apiResource('/categories', CategoriesController::class)->except(['update'])->parameters(['categories' => 'category']);
    Route::put('/categories/{category}', [CategoriesController::class, 'update']);

    Route::get('/products/categories', [ProductsController::class, 'categories']);
    Route::apiResource('/products', ProductsController::class)->except(['update'])->parameters(['products' => 'product']);
    Route::put('/products/{product}', [ProductsController::class, 'update']);

    Route::apiResource('/tables', TablesController::class)->except(['update'])->parameters(['tables' => 'table']);
    Route::put('/tables/{table}', [TablesController::class, 'update']);

    Route::apiResource('/staff', StaffController::class)->except(['update'])->parameters(['staff' => 'user']);
    Route::put('/staff/{user}', [StaffController::class, 'update']);

    Route::get('/analytics/summary', [AnalyticsController::class, 'summary']);
    Route::get('/analytics/top-products', [AnalyticsController::class, 'topProducts']);
    Route::get('/analytics/export/csv', [AnalyticsController::class, 'exportCsv']);
    Route::get('/audit-logs', [AuditLogsController::class, 'index']);
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::post('/inventory/adjust', [InventoryController::class, 'adjust']);
});

// SuperAdmin routes
Route::middleware(['auth:sanctum', 'superadmin'])->prefix('/v1/superadmin')->group(function () {
    Route::get('/restaurants', [TenantManagementController::class, 'index']);
    Route::post('/restaurants', [TenantManagementController::class, 'store']);
    Route::get('/restaurants/{restaurant}', [TenantManagementController::class, 'show']);
    Route::put('/restaurants/{restaurant}/suspend', [TenantManagementController::class, 'suspend']);
    Route::put('/restaurants/{restaurant}/activate', [TenantManagementController::class, 'activate']);
    Route::get('/users', [TenantManagementController::class, 'users']);
    Route::post('/users', [TenantManagementController::class, 'createUser']);
    Route::put('/users/{user}/suspend', [TenantManagementController::class, 'suspendUser']);
});

// Webhooks
Route::post('/v1/webhooks/stripe', [StripeWebhookController::class, 'handle']);
