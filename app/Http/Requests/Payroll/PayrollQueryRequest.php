<?php

namespace App\Http\Requests\Payroll;

use App\Http\Requests\BaseQueryRequest;
use App\Models\Payroll;
use Illuminate\Validation\Rule;

class PayrollQueryRequest extends BaseQueryRequest
{
    protected function queryRules(): array
    {
        return [
            'month' => ['sometimes', 'integer', 'between:1,12'],
            'year' => ['sometimes', 'integer', 'min:2000'],
            'status' => ['sometimes', 'integer', Rule::in([
                Payroll::STATUS_DRAFT,
                Payroll::STATUS_PAID,
                Payroll::STATUS_CANCELLED,
            ])],
            'employee_id' => ['sometimes', 'string', 'exists:employees,id'],
        ];
    }

    public function filters(): array
    {
        return $this->safe()->only([
            'month',
            'year',
            'status',
            'employee_id',
        ]);
    }
}
