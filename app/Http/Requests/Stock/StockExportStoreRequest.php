<?php

namespace App\Http\Requests\Stock;

use App\Models\StockExport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockExportStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'export_date' => ['required', 'date'],
            'purpose' => ['nullable', 'string', 'max:200'],
            'status' => ['sometimes', 'integer', Rule::in([
                StockExport::STATUS_DRAFT,
                StockExport::STATUS_APPROVED,
                StockExport::STATUS_COMPLETED,
            ])],
            'details' => ['required', 'array', 'min:1'],
            'details.*.ingredient_id' => ['required', 'string', 'exists:ingredients,id'],
            'details.*.quantity' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'export_date.required' => 'Export date is required.',
            'details.required' => 'At least one export detail is required.',
            'details.*.ingredient_id.required' => 'Ingredient is required for each detail.',
            'details.*.ingredient_id.exists' => 'Selected ingredient does not exist.',
            'details.*.quantity.required' => 'Quantity is required.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // If status is COMPLETED, validate stock availability
            if ($this->input('status') == StockExport::STATUS_COMPLETED) {
                $details = $this->input('details', []);
                
                foreach ($details as $index => $detail) {
                    $ingredient = \App\Models\Ingredient::find($detail['ingredient_id'] ?? null);
                    
                    if ($ingredient && $ingredient->current_stock < ($detail['quantity'] ?? 0)) {
                        $validator->errors()->add(
                            "details.{$index}.quantity",
                            "Insufficient stock for {$ingredient->name}. Available: {$ingredient->current_stock} {$ingredient->unit}"
                        );
                    }
                }
            }
        });
    }
}
