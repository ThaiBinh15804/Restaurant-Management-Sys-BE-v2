<?php

namespace App\Http\Requests\Payroll;

use App\Models\Payroll;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayrollStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'integer', Rule::in([
                Payroll::STATUS_DRAFT,
                Payroll::STATUS_PAID,
                Payroll::STATUS_CANCELLED,
            ])],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
