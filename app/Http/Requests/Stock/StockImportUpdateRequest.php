<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StockImportUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'import_date' => ['sometimes', 'date'],
            'supplier_id' => ['nullable', 'string', 'exists:suppliers,id'],
            'details' => ['sometimes', 'array', 'min:1'],
            'details.*.id' => ['nullable', 'string', 'exists:stock_import_details,id'],
            'details.*.ingredient_id' => ['required', 'string', 'exists:ingredients,id'],
            'details.*.ordered_quantity' => ['required', 'numeric', 'min:0'],
            'details.*.received_quantity' => ['required', 'numeric', 'min:0'],
            'details.*.unit_price' => ['required', 'numeric', 'min:0'],
            'details.*.delete' => ['sometimes', 'boolean'], // Mark for deletion
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'details.*.ingredient_id.required' => 'Ingredient is required for each detail.',
            'details.*.ingredient_id.exists' => 'Selected ingredient does not exist.',
            'details.*.ordered_quantity.required' => 'Ordered quantity is required.',
            'details.*.received_quantity.required' => 'Received quantity is required.',
            'details.*.unit_price.required' => 'Unit price is required.',
        ];
    }
}
