<?php

namespace App\Http\Requests\Ingredient;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IngredientUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ingredientId = $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('ingredients', 'name')->ignore($ingredientId)],
            'unit' => ['sometimes', 'string', 'max:20'],
            'current_stock' => ['sometimes', 'numeric', 'min:0'],
            'min_stock' => ['sometimes', 'numeric', 'min:0'],
            'max_stock' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'This ingredient name already exists.',
            'max_stock.gte' => 'Maximum stock must be greater than or equal to minimum stock.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $maxStock = $this->input('max_stock');
            $minStock = $this->input('min_stock');
            
            // If both are provided, validate max > min
            if ($maxStock !== null && $minStock !== null && $maxStock <= $minStock) {
                $validator->errors()->add('max_stock', 'Maximum stock must be greater than minimum stock.');
            }
        });
    }
}
