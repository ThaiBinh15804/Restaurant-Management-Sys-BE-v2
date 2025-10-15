<?php

namespace App\Http\Requests\Employee;

use App\Http\Requests\BaseQueryRequest;
use App\Models\Employee;
use Illuminate\Validation\Rule;

class EmployeeQueryRequest extends BaseQueryRequest
{
    protected function prepareForValidation()
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->query('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

    protected function queryRules(): array
    {
        return [
            'full_name' => ['sometimes', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'contract_type' => ['sometimes', 'integer', Rule::in([
                Employee::CONTRACT_FULL_TIME,
                Employee::CONTRACT_PART_TIME,
            ])],
            'gender' => ['sometimes', 'string', 'max:20'],
            'hire_date_from' => ['sometimes', 'date'],
            'hire_date_to' => ['sometimes', 'date', 'after_or_equal:hire_date_from'],
            'role_id' => ['sometimes', 'nullable', 'string', 'exists:roles,id'],
        ];
    }

    public function filters(): array
    {
        return $this->safe()->only([
            'full_name',
            'is_active',
            'contract_type',
            'gender',
            'hire_date_from',
            'hire_date_to',
            'role_id',
        ]);
    }
}
