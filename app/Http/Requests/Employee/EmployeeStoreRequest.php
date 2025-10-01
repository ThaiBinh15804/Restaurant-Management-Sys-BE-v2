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
            'user_id' => ['nullable', 'string', 'exists:users,id', 'unique:employees,user_id'],
        ];
    }
}
