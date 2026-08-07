<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TenantManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 15);
        $perPage = min((int) $perPage, 100);

        $restaurants = Restaurant::withoutGlobalScopes()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => $restaurants->items(),
            'meta' => [
                'current_page' => $restaurants->currentPage(),
                'last_page' => $restaurants->lastPage(),
                'per_page' => $restaurants->perPage(),
                'total' => $restaurants->total(),
            ],
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:155',
            'slug' => 'required|string|max:155|unique:restaurants,slug',
            'owner_email' => 'required|email|max:155',
            'owner_name' => 'required|string|max:155',
            'owner_password' => 'required|string|min:8',
            'plan_name' => 'nullable|string|max:50',
        ]);

        $result = DB::transaction(function () use ($request) {
            $owner = User::create([
                'name' => $request->input('owner_name'),
                'email' => $request->input('owner_email'),
                'password' => Hash::make($request->input('owner_password')),
                'role' => 'owner',
            ]);

            $restaurant = Restaurant::create([
                'owner_id' => $owner->id,
                'name' => $request->input('name'),
                'slug' => $request->input('slug'),
                'status' => 'active',
            ]);

            $owner->update(['restaurant_id' => $restaurant->id]);

            if ($request->filled('plan_name')) {
                $owner->subscription()->create([
                    'restaurant_id' => $restaurant->id,
                    'plan_name' => $request->input('plan_name'),
                    'status' => 'active',
                ]);
            }

            return ['owner' => $owner, 'restaurant' => $restaurant];
        });

        return response()->json([
            'data' => [
                'id' => $result['restaurant']->id,
                'name' => $result['restaurant']->name,
                'slug' => $result['restaurant']->slug,
                'status' => $result['restaurant']->status,
                'owner' => [
                    'id' => $result['owner']->id,
                    'name' => $result['owner']->name,
                    'email' => $result['owner']->email,
                ],
            ],
        ], 201);
    }

    public function show(Restaurant $restaurant): JsonResponse
    {
        $restaurant->loadMissing(['owner', 'subscription', 'users']);

        return response()->json([
            'data' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
                'status' => $restaurant->status,
                'weekend_mode' => $restaurant->weekend_mode,
                'owner' => $restaurant->owner !== null ? [
                    'id' => $restaurant->owner->id,
                    'name' => $restaurant->owner->name,
                    'email' => $restaurant->owner->email,
                ] : null,
                'subscription' => $restaurant->subscription !== null ? [
                    'id' => $restaurant->subscription->id,
                    'plan_name' => $restaurant->subscription->plan_name,
                    'status' => $restaurant->subscription->status,
                    'ends_at' => $restaurant->subscription->ends_at,
                ] : null,
                'users_count' => $restaurant->users()->count(),
                'created_at' => $restaurant->created_at,
                'updated_at' => $restaurant->updated_at,
            ],
        ], 200);
    }

    public function suspend(Restaurant $restaurant): JsonResponse
    {
        $restaurant->update(['status' => 'suspended']);

        return response()->json([
            'data' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
                'status' => $restaurant->status,
            ],
        ], 200);
    }

    public function activate(Restaurant $restaurant): JsonResponse
    {
        $restaurant->update(['status' => 'active']);

        return response()->json([
            'data' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
                'status' => $restaurant->status,
            ],
        ], 200);
    }

    public function users(Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 15);
        $perPage = min((int) $perPage, 100);

        $users = User::withoutGlobalScopes()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ], 200);
    }

    public function createUser(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:155',
            'email' => 'required|email|max:155|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:owner,waiter,kitchen,bar,superadmin',
            'restaurant_id' => 'nullable|integer|exists:restaurants,id',
        ]);

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'role' => $request->input('role'),
            'restaurant_id' => $request->input('restaurant_id'),
        ]);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'restaurant_id' => $user->restaurant_id,
            ],
        ], 201);
    }

    public function suspendUser(User $user): JsonResponse
    {
        if ($user->role === 'superadmin') {
            return response()->json(['message' => 'Cannot suspend a superadmin.'], 422);
        }

        $user->update(['role' => 'suspended']);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'restaurant_id' => $user->restaurant_id,
            ],
        ], 200);
    }
}
