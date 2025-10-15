<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StockLossStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ingredient_id' => ['required', 'string', 'exists:ingredients,id'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:200'],
            'loss_date' => ['required', 'date'],
            'employee_id' => ['nullable', 'string', 'exists:employees,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'ingredient_id.required' => 'Ingredient is required.',
            'ingredient_id.exists' => 'Selected ingredient does not exist.',
            'quantity.required' => 'Quantity is required.',
            'loss_date.required' => 'Loss date is required.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $ingredient = \App\Models\Ingredient::find($this->input('ingredient_id'));
            $quantity = $this->input('quantity', 0);
            
            if ($ingredient && $ingredient->current_stock < $quantity) {
                $validator->errors()->add(
                    'quantity',
                    "Insufficient stock for {$ingredient->name}. Available: {$ingredient->current_stock} {$ingredient->unit}"
                );
            }
        });
    }
}
