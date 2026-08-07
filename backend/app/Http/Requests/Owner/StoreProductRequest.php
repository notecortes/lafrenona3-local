<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|array|min:1',
            'name.*' => 'required|string|max:100',
            'description' => 'nullable|array',
            'description.*' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0.01',
            'weekend_price' => 'nullable|numeric|min:0.01',
            'image_url' => 'nullable|url|max:500',
            'stock_status' => 'sometimes|in:available,out_of_stock',
            'is_active' => 'nullable|boolean',
            'is_vegan' => 'nullable|boolean',
            'is_vegetarian' => 'nullable|boolean',
            'allergens' => 'nullable|array',
            'allergens.*' => 'string|max:50',
        ];
    }
}
