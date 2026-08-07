<?php

declare(strict_types=1);

namespace App\Http\Resources\Owner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MostSoldProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $name = $this['product_name'] ?? '';
        if (is_string($name) && str_starts_with($name, '{')) {
            $decoded = json_decode($name, true);
            if (is_array($decoded) && isset($decoded['en'])) {
                $name = $decoded['en'];
            }
        }
        return [
            'product_name' => $name,
            'total_quantity' => (int) $this['total_quantity'],
            'total_revenue' => (float) $this['total_revenue'],
        ];
    }
}
