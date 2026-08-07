<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\StoreStaffRequest;
use App\Http\Resources\Owner\StaffResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller
{
    public function index(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $staff = User::where('restaurant_id', $request->user()->restaurant_id)
            ->where('role', '!=', 'superadmin')
            ->where('role', '!=', 'owner')
            ->get();

        return StaffResource::collection($staff);
    }

    public function store(StoreStaffRequest $request): StaffResource
    {
        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'role' => $request->input('role'),
            'restaurant_id' => $request->user()->restaurant_id,
        ]);

        return new StaffResource($user);
    }

    public function show(User $user): StaffResource
    {
        $request = request();
        if ($user->restaurant_id !== $request->user()->restaurant_id) {
            abort(404);
        }

        return new StaffResource($user);
    }

    public function update(StoreStaffRequest $request, User $user): StaffResource
    {
        if ($user->restaurant_id !== $request->user()->restaurant_id) {
            abort(404);
        }

        $updateData = [
            'name' => $request->input('name', $user->name),
            'role' => $request->input('role', $user->role),
        ];

        if ($request->filled('email')) {
            $updateData['email'] = $request->input('email');
        }

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->input('password'));
        }

        $user->update($updateData);

        return new StaffResource($user);
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->restaurant_id !== request()->user()->restaurant_id) {
            abort(404);
        }

        $user->delete();

        return response()->json(['message' => 'Staff member deleted.'], 200);
    }
}
