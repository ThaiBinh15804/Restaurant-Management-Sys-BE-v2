<?php

namespace App\Http\Requests\Ingredient;

use Illuminate\Foundation\Http\FormRequest;

class IngredientStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ingredient_category_id' => ['sometimes', 'nullable', 'string', 'exists:ingredient_categories,id'],
            'name' => ['required', 'string', 'max:100', 'unique:ingredients,name'],
            'unit' => ['required', 'string', 'max:20'],
            'current_stock' => ['sometimes', 'numeric', 'min:0'],
            'min_stock' => ['required', 'numeric', 'min:0'],
            'max_stock' => ['nullable', 'numeric', 'min:0', 'gt:min_stock'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:2048'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Ingredient name is required.',
            'name.unique' => 'This ingredient name already exists.',
            'unit.required' => 'Unit of measurement is required.',
            'min_stock.required' => 'Minimum stock level is required.',
            'max_stock.gt' => 'Maximum stock must be greater than minimum stock.',
            'image.image' => 'The ingredient image must be an image file.',
            'image.mimes' => 'The ingredient image must be a file of type: jpeg, jpg, png, gif, webp.',
            'image.max' => 'The ingredient image may not be greater than 2MB.',
        ];
    }
}
