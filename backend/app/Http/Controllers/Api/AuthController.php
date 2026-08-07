<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:155',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        $passwordValid = $user && Hash::check($request->password, $user->password);

        if (! $user || ! $passwordValid) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'restaurant_id' => $user->restaurant_id,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.'], 200);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $restaurant = null;

        if ($user->restaurant_id !== null) {
            $restaurant = \App\Models\Restaurant::withoutGlobalScopes()
                ->find($user->restaurant_id);
        }

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'restaurant_id' => $user->restaurant_id,
                'restaurant' => $restaurant !== null ? [
                    'id' => $restaurant->id,
                    'name' => $restaurant->name,
                    'slug' => $restaurant->slug,
                    'status' => $restaurant->status,
                ] : null,
            ],
        ], 200);
    }
}
