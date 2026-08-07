<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TenantContext;
use Illuminate\Console\Command;

class TenantSet extends Command
{
    protected $signature = 'tenant:set {restaurant_id : The ID of the restaurant to set as the current tenant}';

    protected $description = 'Set the current tenant context for CLI operations';

    public function handle(TenantContext $tenantContext): int
    {
        $restaurantId = (int) $this->argument('restaurant_id');

        $tenantContext->setTenant($restaurantId);

        $this->info("Tenant context set to restaurant ID: {$restaurantId}");

        return Command::SUCCESS;
    }
}
