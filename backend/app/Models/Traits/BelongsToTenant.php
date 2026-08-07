<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Models\Scopes\TenantScope;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function restaurant()
    {
        return $this->belongsTo(\App\Models\Restaurant::class);
    }
}
