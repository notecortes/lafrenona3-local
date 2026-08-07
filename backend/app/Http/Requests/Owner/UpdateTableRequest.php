<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number' => 'sometimes|string|max:20|unique:tables,number,' . $this->table->id,
            'status' => 'sometimes|in:free,occupied',
        ];
    }
}
