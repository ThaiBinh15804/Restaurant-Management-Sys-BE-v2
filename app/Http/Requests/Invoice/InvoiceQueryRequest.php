<?php

namespace App\Http\Requests\Invoice;

use App\Http\Requests\BaseQueryRequest;

class InvoiceQueryRequest extends BaseQueryRequest
{
    /**
     * Các rules validate cho query params của Invoice.
     */
    protected function queryRules(): array
    {
        return [
            'table_session_id' => ['sometimes', 'string', 'max:255'],
            'status'           => ['sometimes', 'integer', 'min:0'],
            'total_amount_min' => ['sometimes', 'numeric', 'min:0'],
            'total_amount_max' => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    /**
     * Lấy các field filter hợp lệ từ query params.
     */
    public function filters(): array
    {
        return $this->safe()->only([
            'table_session_id',
            'status',
            'total_amount_min',
            'total_amount_max',
        ]);
    }
}
