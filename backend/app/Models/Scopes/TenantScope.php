<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app('tenant.context')->resolve();

        if ($tenantId === null) {
            return;
        }

        $builder->where($model->getTable() . '.restaurant_id', $tenantId);
    }
}
