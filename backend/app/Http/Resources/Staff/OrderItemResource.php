<?php

declare(strict_types=1);

namespace App\Http\Resources\Staff;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_number' => $this->order->id,
            'table_number' => $this->order->table->number ?? null,
            'product_id' => $this->product_id,
            'product_name' => is_array($this->product->name)
                ? ($this->product->name['en'] ?? $this->product->name[array_keys($this->product->name)[0]] ?? '')
                : $this->product->name,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'notes' => $this->notes,
            'status' => $this->status,
            'target_area' => $this->target_area,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
