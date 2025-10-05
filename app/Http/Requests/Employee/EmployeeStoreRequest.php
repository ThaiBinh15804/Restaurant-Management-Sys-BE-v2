<?php

namespace App\Http\Requests\Employee;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Employee information
            'full_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:15'],
            'gender' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:200'],
            'bank_account' => ['nullable', 'string', 'max:100'],
            'contract_type' => ['required', 'integer', Rule::in([
                Employee::CONTRACT_FULL_TIME,
                Employee::CONTRACT_PART_TIME,
            ])],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'hire_date' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            
            // User account information (required for creating login credentials)
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role_id' => ['required', 'string', 'exists:roles,id'],
            
            // Optional: Link to existing user (if provided, email/password will be ignored)
            'user_id' => ['nullable', 'string', 'exists:users,id', 'unique:employees,user_id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email address is required for creating user account.',
            'email.unique' => 'This email address is already registered.',
            'password.required' => 'Password is required for creating user account.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'role_id.required' => 'Role is required for user account.',
            'role_id.exists' => 'The selected role does not exist.',
        ];
    }
}
