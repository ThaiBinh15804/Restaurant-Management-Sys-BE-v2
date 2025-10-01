<?php

namespace App\Http\Requests\PayrollItem;

use App\Models\PayrollItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayrollItemUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_type' => ['sometimes', 'integer', Rule::in([
                PayrollItem::TYPE_EARNING,
                PayrollItem::TYPE_DEDUCTION,
            ])],
            'code' => ['sometimes', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
