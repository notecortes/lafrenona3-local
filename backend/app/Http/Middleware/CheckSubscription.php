<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Restaurant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
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
            ?? $request->input('restaurant_id')
            ?? $user->restaurant_id;

        if ($restaurantId === null) {
            return response()->json(['message' => 'No restaurant assigned.'], 403);
        }

        $restaurant = Restaurant::withoutGlobalScopes()->find($restaurantId);

        if ($restaurant === null) {
            return response()->json(['message' => 'Restaurant not found.'], 404);
        }

        if ($restaurant->status === 'suspended') {
            return response()->json(['message' => 'This restaurant is suspended.'], 403);
        }

        $subscription = $restaurant->owner->subscription;

        if ($subscription !== null && $subscription->isSuspended()) {
            return response()->json(['message' => 'Subscription is not active.'], 403);
        }

        return $next($request);
    }
}
