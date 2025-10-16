<?php

namespace App\Http\Requests\TableSession;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class SplitTableRequest extends FormRequest
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
            'source_session_id' => [
                'required',
                'string',
                'exists:table_sessions,id',
            ],
            'order_items' => [
                'required',
                'array',
                'min:1',
            ],
            'order_items.*.order_item_id' => [
                'required',
                'string',
                'exists:order_items,id',
            ],
            'order_items.*.quantity_to_transfer' => [
                'required',
                'integer',
                'min:1',
            ],
            'target_session_id' => [
                'nullable',
                'string',
                'exists:table_sessions,id',
                'different:source_session_id',
            ],
            'target_dining_table_id' => [
                'nullable',
                'string',
                'exists:dining_tables,id',
                'required_without:target_session_id',
            ],
            'note' => [
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
            'source_session_id.required' => 'Source session ID is required',
            'source_session_id.exists' => 'Source session does not exist',
            'order_items.required' => 'Order items are required',
            'order_items.min' => 'At least one order item is required',
            'order_items.*.order_item_id.required' => 'Order item ID is required',
            'order_items.*.order_item_id.exists' => 'Order item does not exist',
            'order_items.*.quantity_to_transfer.required' => 'Quantity to transfer is required',
            'order_items.*.quantity_to_transfer.min' => 'Quantity must be at least 1',
            'target_session_id.exists' => 'Target session does not exist',
            'target_session_id.different' => 'Target session must be different from source session',
            'target_dining_table_id.exists' => 'Target dining table does not exist',
            'target_dining_table_id.required_without' => 'Either target session or dining table is required',
            'employee_id.required' => 'Employee ID is required',
            'employee_id.exists' => 'Employee does not exist',
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
            $sourceSessionId = $this->input('source_session_id');
            $orderItems = $this->input('order_items', []);

            // 1. Kiểm tra order items thuộc source session
            foreach ($orderItems as $index => $item) {
                $orderItemId = $item['order_item_id'] ?? null;
                $qtyToTransfer = $item['quantity_to_transfer'] ?? 0;

                if ($orderItemId) {
                    $orderItem = DB::table('order_items')
                        ->join('orders', 'order_items.order_id', '=', 'orders.id')
                        ->where('order_items.id', $orderItemId)
                        ->where('orders.table_session_id', $sourceSessionId)
                        ->select('order_items.quantity', 'order_items.id')
                        ->first();

                    if (!$orderItem) {
                        $validator->errors()->add(
                            "order_items.{$index}.order_item_id",
                            "Order item does not belong to source session"
                        );
                    } elseif ($qtyToTransfer > $orderItem->quantity) {
                        $validator->errors()->add(
                            "order_items.{$index}.quantity_to_transfer",
                            "Quantity to transfer ({$qtyToTransfer}) exceeds available quantity ({$orderItem->quantity})"
                        );
                    }
                }
            }

            // 2. Kiểm tra remaining_amount > transferred_items_total
            if ($validator->errors()->isEmpty()) {
                $invoice = Invoice::where('table_session_id', $sourceSessionId)
                    ->whereNull('merged_invoice_id')
                    ->first();

                if ($invoice) {
                    $remainingAmount = $invoice->remaining_amount;
                    
                    // Tính tổng giá trị món tách
                    $transferredTotal = 0;
                    foreach ($orderItems as $item) {
                        $orderItem = DB::table('order_items')
                            ->where('id', $item['order_item_id'])
                            ->first();
                        
                        if ($orderItem) {
                            $unitPrice = $orderItem->total_price / $orderItem->quantity;
                            $transferredTotal += $unitPrice * $item['quantity_to_transfer'];
                        }
                    }

                    if ($transferredTotal >= $remainingAmount) {
                        $validator->errors()->add(
                            'order_items',
                            sprintf(
                                'Total transferred amount (%.2f) must be less than remaining amount (%.2f)',
                                $transferredTotal,
                                $remainingAmount
                            )
                        );
                    }
                }
            }

            // 3. Kiểm tra ít nhất 1 món phải còn lại ở source
            $totalItemsInSource = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.table_session_id', $sourceSessionId)
                ->count();

            $fullyTransferredCount = 0;
            foreach ($orderItems as $item) {
                $orderItem = DB::table('order_items')
                    ->where('id', $item['order_item_id'])
                    ->first();
                
                if ($orderItem && $item['quantity_to_transfer'] >= $orderItem->quantity) {
                    $fullyTransferredCount++;
                }
            }

            if ($fullyTransferredCount >= $totalItemsInSource) {
                $validator->errors()->add(
                    'order_items',
                    'At least one item must remain in the source table'
                );
            }
        });
    }
}

