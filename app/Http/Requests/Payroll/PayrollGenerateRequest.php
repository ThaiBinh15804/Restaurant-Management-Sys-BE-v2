<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class PayrollGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2000'],
            'employee_ids' => ['sometimes', 'array'],
            'employee_ids.*' => ['string', 'exists:employees,id'],
            'overwrite' => ['sometimes', 'boolean'],
        ];
    }
}
