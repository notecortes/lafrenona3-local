<?php

declare(strict_types=1);

namespace App\Http\Resources\Owner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'restaurant_id' => $this->restaurant_id,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'weekend_price' => $this->weekend_price,
            'image_url' => $this->image_url,
            'stock_status' => $this->stock_status,
            'is_active' => $this->is_active,
            'is_vegan' => $this->is_vegan,
            'is_vegetarian' => $this->is_vegetarian,
            'allergens' => $this->allergens,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
