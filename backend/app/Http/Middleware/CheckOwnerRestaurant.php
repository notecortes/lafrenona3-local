<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOwnerRestaurant
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

        $restaurantId = $request->route('restaurant')
            ?? $request->input('restaurant_id');

        if ($restaurantId === null) {
            $restaurantId = $user->restaurant_id;
        }

        if ($restaurantId === null) {
            return response()->json(['message' => 'No restaurant assigned.'], 403);
        }

        if ((int) $restaurantId !== (int) $user->restaurant_id) {
            return response()->json(['message' => 'Access denied. You do not own this restaurant.'], 403);
        }

        return $next($request);
    }
}
