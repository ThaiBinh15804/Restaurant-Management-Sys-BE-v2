<?php

namespace App\Http\Requests\PayrollItem;

use App\Models\PayrollItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayrollItemStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payroll_id' => ['required', 'string', 'exists:payrolls,id'],
            'item_type' => ['required', 'integer', Rule::in([
                PayrollItem::TYPE_EARNING,
                PayrollItem::TYPE_DEDUCTION,
            ])],
            'code' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
