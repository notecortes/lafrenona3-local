<?php

declare(strict_types=1);

use App\Events\OrderStateChanged;
use App\Listeners\ProcessInventoryDeduction;

return [

    OrderStateChanged::class => [
        ProcessInventoryDeduction::class,
    ],

];
