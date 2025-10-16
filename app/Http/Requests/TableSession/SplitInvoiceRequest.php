<?php

namespace App\Http\Requests\TableSession;

use Illuminate\Foundation\Http\FormRequest;

class SplitInvoiceRequest extends FormRequest
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
            'invoice_id' => [
                'required',
                'string',
                'exists:invoices,id',
            ],
            'splits' => [
                'required',
                'array',
                'min:1',
                'max:10',
            ],
            'splits.*.percentage' => [
                'required',
                'numeric',
                'min:0.01',
                'max:99.99',
            ],
            'splits.*.note' => [
                'nullable',
                'string',
                'max:500',
            ],
            'employee_id' => [
                'required',
                'string',
                'exists:employees,id',
            ],
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
            'invoice_id.required' => 'Invoice ID is required',
            'invoice_id.exists' => 'Invoice does not exist',
            'splits.required' => 'Split details are required',
            'splits.array' => 'Splits must be an array',
            'splits.min' => 'At least one split is required',
            'splits.max' => 'Maximum 10 splits allowed',
            'splits.*.percentage.required' => 'Percentage is required for each split',
            'splits.*.percentage.numeric' => 'Percentage must be a number',
            'splits.*.percentage.min' => 'Percentage must be at least 0.01%',
            'splits.*.percentage.max' => 'Percentage must not exceed 99.99%',
            'employee_id.required' => 'Employee ID is required',
            'employee_id.exists' => 'Employee does not exist',
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
            'invoice_id' => 'invoice',
            'splits' => 'split details',
            'employee_id' => 'employee',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $splits = $this->input('splits', []);
            
            // Kiểm tra tổng % không vượt quá 100%
            $totalPercentage = collect($splits)->sum('percentage');
            
            if ($totalPercentage >= 100) {
                $validator->errors()->add(
                    'splits',
                    "Total split percentage ({$totalPercentage}%) must be less than 100%"
                );
            }

            if ($totalPercentage <= 0) {
                $validator->errors()->add(
                    'splits',
                    "Total split percentage must be greater than 0%"
                );
            }
        });
    }
}
