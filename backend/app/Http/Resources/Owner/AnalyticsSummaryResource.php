<?php

declare(strict_types=1);

namespace App\Http\Resources\Owner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalyticsSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total_revenue' => $this['total_revenue'],
            'avg_ticket' => $this['avg_ticket'],
            'total_orders' => $this['total_orders'],
            'total_items_sold' => $this['total_items_sold'],
            'date_range' => $this['date_range'],
            'peak_hours' => $this['peak_hours'] ?? [],
        ];
    }
}
