<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:155',
            'email' => 'required|email|max:155|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(['waiter', 'kitchen', 'bar'])],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules['email'] = [
                'sometimes',
                'email',
                'max:155',
                Rule::unique('users', 'email')->ignore($this->user),
            ];

            $rules['password'] = 'nullable|string|min:8';
        }

        return $rules;
    }
}
