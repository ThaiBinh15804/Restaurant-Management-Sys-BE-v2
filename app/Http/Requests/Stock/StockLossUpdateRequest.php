<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StockLossUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ingredient_id' => ['sometimes', 'string', 'exists:ingredients,id'],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:200'],
            'loss_date' => ['sometimes', 'date'],
            'employee_id' => ['nullable', 'string', 'exists:employees,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'ingredient_id.exists' => 'Selected ingredient does not exist.',
            'employee_id.exists' => 'Selected employee does not exist.',
        ];
    }
}
