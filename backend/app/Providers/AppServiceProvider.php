<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\OrderStateChanged;
use App\Listeners\ProcessInventoryDeduction;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->listen();
        RateLimiter::for('default', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('client_routes', function (Request $request) {
            return Limit::perMinute(100)->by($request->ip());
        });

        RateLimiter::for('auth_login', function (Request $request) {
            return Limit::perMinute(10)->by($request->email ?: $request->ip());
        });

        RateLimiter::for('offline_sync', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('superadmin', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }

    protected function listen(): void
    {
        $this->app['events']->listen(
            OrderStateChanged::class,
            ProcessInventoryDeduction::class,
        );
    }
}
