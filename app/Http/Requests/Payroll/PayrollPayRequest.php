<?php

namespace App\Http\Requests\Payroll;

use App\Models\Payroll;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayrollPayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'integer', Rule::in([
                Payroll::PAYMENT_CASH,
                Payroll::PAYMENT_BANK_TRANSFER,
                Payroll::PAYMENT_CREDIT_CARD,
                Payroll::PAYMENT_E_WALLET,
            ])],
            'payment_ref' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['sometimes', 'date_format:Y-m-d H:i:s'],
            'paid_by' => ['sometimes', 'string', 'exists:employees,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
