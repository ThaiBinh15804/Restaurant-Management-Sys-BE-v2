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
            'full_name' => ['sometimes', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:15'],
            'gender' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:200'],
            'bank_account' => ['nullable', 'string', 'max:100'],
            'contract_type' => ['sometimes', 'integer', Rule::in([
                Employee::CONTRACT_FULL_TIME,
                Employee::CONTRACT_PART_TIME,
            ])],
            'base_salary' => ['sometimes', 'numeric', 'min:0'],
            'hire_date' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'user_id' => ['nullable', 'string', 'exists:users,id', Rule::unique('employees', 'user_id')->ignore($employeeId, 'id')],
            
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
        ];
    }
}
