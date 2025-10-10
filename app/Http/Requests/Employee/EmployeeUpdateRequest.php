<?php

namespace App\Http\Requests\Employee;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employeeId = $this->route('id') ?? $this->route('employee');

        return [
            // Employee information
            'full_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:15'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:20'],
            'address' => ['sometimes', 'nullable', 'string', 'max:200'],
            'bank_account' => ['sometimes', 'nullable', 'string', 'max:100'],
            'contract_type' => ['sometimes', 'nullable', 'integer', Rule::in([
                Employee::CONTRACT_FULL_TIME,
                Employee::CONTRACT_PART_TIME,
            ])],
            'base_salary' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'hire_date' => ['sometimes', 'nullable', 'date'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'user_id' => ['sometimes', 'nullable', 'string', 'exists:users,id', Rule::unique('employees', 'user_id')->ignore($employeeId, 'id')],
            
            // Avatar upload
            'avatar' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:2048'],
        ];
    }
    
    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'This email address is already taken.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'avatar.image' => 'The avatar must be an image file.',
            'avatar.mimes' => 'The avatar must be a file of type: jpeg, jpg, png, gif, webp.',
            'avatar.max' => 'The avatar may not be greater than 2MB.',
        ];
    }
}
