<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

class TenantContext
{
    protected ?int $cachedTenantId = null;

    protected ?User $cachedUser = null;

    public function resolve(?User $user = null): ?int
    {
        if ($user === null) {
            $user = auth()->user();
        }

        if ($user === null) {
            return null;
        }

        if ($this->cachedUser?->is($user) && $this->cachedTenantId !== null) {
            return $this->cachedTenantId;
        }

        $this->cachedUser = $user;
        $this->cachedTenantId = $this->resolveForUser($user);

        return $this->cachedTenantId;
    }

    protected function resolveForUser(User $user): ?int
    {
        if ($user->role === 'superadmin') {
            return null;
        }

        return $user->restaurant_id;
    }

    public function setTenant(int $restaurantId): void
    {
        $this->cachedTenantId = $restaurantId;
        $this->cachedUser = null;
    }

    public function forget(): void
    {
        $this->cachedTenantId = null;
        $this->cachedUser = null;
    }

    public function get(): ?int
    {
        return $this->cachedTenantId;
    }
}
