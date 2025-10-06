<?php

namespace App\Http\Requests\PayrollItem;

use App\Http\Requests\BaseQueryRequest;
use App\Models\PayrollItem;
use Illuminate\Validation\Rule;

class PayrollItemQueryRequest extends BaseQueryRequest
{
    protected function queryRules(): array
    {
        return [
            'payroll_id' => ['sometimes', 'string', 'exists:payrolls,id'],
            'item_type' => ['sometimes', 'integer', Rule::in([
                PayrollItem::TYPE_EARNING,
                PayrollItem::TYPE_DEDUCTION,
            ])],
            'code' => ['sometimes', 'string', 'max:50'],
        ];
    }

    public function filters(): array
    {
        return $this->safe()->only([
            'payroll_id',
            'item_type',
            'code',
        ]);
    }
}
