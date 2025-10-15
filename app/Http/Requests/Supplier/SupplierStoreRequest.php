<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class SupplierStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'contact_person_name' => ['nullable', 'string', 'max:100'],
            'contact_person_phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'email', 'max:100'],
            'address' => ['nullable', 'string', 'max:200'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Supplier name is required.',
            'email.email' => 'Please provide a valid email address.',
        ];
    }
}
