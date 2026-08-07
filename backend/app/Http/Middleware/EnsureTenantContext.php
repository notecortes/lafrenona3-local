<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->role === 'superadmin') {
            return $next($request);
        }

        if ($user->restaurant_id === null) {
            return response()->json(['message' => 'No restaurant assigned.'], 403);
        }

        app('tenant.context')->setTenant($user->restaurant_id);

        return $next($request);
    }
}
