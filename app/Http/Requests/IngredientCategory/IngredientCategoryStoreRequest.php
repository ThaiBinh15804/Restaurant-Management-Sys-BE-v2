<?php

namespace App\Http\Requests\IngredientCategory;

use Illuminate\Foundation\Http\FormRequest;

class IngredientCategoryStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                'unique:ingredient_categories,name',
            ],
            'is_active' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'tên danh mục',
            'is_active' => 'trạng thái hoạt động',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => ':attribute không được bỏ trống.',
            'name.unique' => ':attribute đã tồn tại trong hệ thống.',
            'name.max' => ':attribute không được vượt quá :max ký tự.',
        ];
    }
}
