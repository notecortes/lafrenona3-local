<?php

declare(strict_types=1);

use App\Http\Middleware\CheckOwnerRestaurant;
use App\Http\Middleware\CheckSubscription;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\SecurityHeaders;
use App\Services\TenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withEvents(true)
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant.context' => EnsureTenantContext::class,
            'check.owner.restaurant' => CheckOwnerRestaurant::class,
            'check.subscription' => CheckSubscription::class,
            'superadmin' => EnsureSuperAdmin::class,
            'security.headers' => SecurityHeaders::class,
        ]);

        $middleware->api([
            SecurityHeaders::class,
        ]);

        app()->singleton(TenantContext::class, function (): TenantContext {
            return new TenantContext();
        });

        app()->alias(TenantContext::class, 'tenant.context');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
