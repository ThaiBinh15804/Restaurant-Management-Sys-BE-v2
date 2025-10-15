<?php

namespace App\Http\Requests\Stock;

use App\Models\StockExport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockExportUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'export_date' => ['sometimes', 'date'],
            'purpose' => ['nullable', 'string', 'max:200'],
            'status' => ['sometimes', 'integer', Rule::in([
                StockExport::STATUS_DRAFT,
                StockExport::STATUS_APPROVED,
                StockExport::STATUS_COMPLETED,
            ])],
            'details' => ['sometimes', 'array', 'min:1'],
            'details.*.id' => ['nullable', 'string', 'exists:stock_export_details,id'],
            'details.*.ingredient_id' => ['required', 'string', 'exists:ingredients,id'],
            'details.*.quantity' => ['required', 'numeric', 'min:0'],
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
            'details.*.quantity.required' => 'Quantity is required.',
        ];
    }
}
