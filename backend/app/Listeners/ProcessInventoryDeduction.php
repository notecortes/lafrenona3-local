<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderStateChanged;
use App\Models\OrderItem;
use App\Services\InventoryStockService;

class ProcessInventoryDeduction
{
    public function __construct()
    {
    }

    public function handle(OrderStateChanged $event): void
    {
        $orderItem = $event->orderItem;

        if ($event->previousStatus === 'pending' && $orderItem->status === 'cooking') {
            $service = app(InventoryStockService::class);
            $service->deductStock($orderItem);
        }
    }
}
