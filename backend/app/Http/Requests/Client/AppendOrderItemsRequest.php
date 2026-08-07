<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class AppendOrderItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|decimal:0,2|min:0',
            'items.*.notes' => 'nullable|string|max:1000',
            'items.*.target_area' => 'nullable|in:kitchen,bar',
            'items.*.idempotency_key' => 'nullable|string|max:64',
        ];
    }
}
