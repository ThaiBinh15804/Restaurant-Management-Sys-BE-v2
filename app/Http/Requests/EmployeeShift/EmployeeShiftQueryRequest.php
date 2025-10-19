<?php

namespace App\Http\Requests\EmployeeShift;

use App\Http\Requests\BaseQueryRequest;
use App\Models\EmployeeShift;
use Illuminate\Validation\Rule;

class EmployeeShiftQueryRequest extends BaseQueryRequest
{
    protected function queryRules(): array
    {
        return [
            'employee_id' => ['sometimes', 'string', 'exists:employees,id'],
            'shift_id' => ['sometimes', 'string', 'exists:shifts,id'],
            'status' => ['sometimes', 'integer', Rule::in([
                EmployeeShift::STATUS_SCHEDULED,
                EmployeeShift::STATUS_PRESENT,
                EmployeeShift::STATUS_LATE,
                EmployeeShift::STATUS_EARLY_LEAVE,
                EmployeeShift::STATUS_ABSENT,
            ])],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
        ];
    }

    public function filters(): array
    {
        return $this->safe()->only([
            'employee_id',
            'shift_id',
            'status',
            'date_from',
            'date_to',
        ]);
    }
}
