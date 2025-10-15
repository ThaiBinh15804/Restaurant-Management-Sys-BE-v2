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
            'splits.*.order_item_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'splits.*.order_item_ids.*' => [
                'required',
                'string',
                'exists:order_items,id',
                'distinct',
            ],
            'splits.*.note' => [
                'nullable',
                'string',
                'max:255',
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
            'splits.*.order_item_ids.required' => 'Order items are required for each split',
            'splits.*.order_item_ids.*.exists' => 'One or more order items do not exist',
            'splits.*.order_item_ids.*.distinct' => 'Order items must be unique within a split',
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
            
            // Kiểm tra không có order_item_id trùng lặp giữa các splits
            $allOrderItemIds = [];
            foreach ($splits as $index => $split) {
                $orderItemIds = $split['order_item_ids'] ?? [];
                
                foreach ($orderItemIds as $itemId) {
                    if (in_array($itemId, $allOrderItemIds)) {
                        $validator->errors()->add(
                            "splits.{$index}.order_item_ids",
                            "Order item {$itemId} is already included in another split"
                        );
                    }
                    $allOrderItemIds[] = $itemId;
                }
            }

            // TODO: Validate rằng tất cả order_item_ids thuộc về invoice_id
            // (Cần query database để check)
        });
    }
}
