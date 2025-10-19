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
            'name' => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('ingredients', 'name')->ignore($ingredientId)],
            'unit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'current_stock' => ['sometimes', 'nullable','numeric', 'min:0'],
            'min_stock' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'max_stock' => ['nullable', 'nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:2048'],
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
            'image.image' => 'The ingredient image must be an image file.',
            'image.mimes' => 'The ingredient image must be a file of type: jpeg, jpg, png, gif, webp.',
            'image.max' => 'The ingredient image may not be greater than 2MB.',
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
