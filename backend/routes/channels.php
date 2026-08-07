<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The "presence" channel provides authentication
| for users to join real-time channels used by other users.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('restaurant.{restaurantId}', function (User $user, int $restaurantId) {
    if ($user->role === 'superadmin') {
        return true;
    }

    return (int) $user->restaurant_id === $restaurantId;
});
