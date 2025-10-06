<?php

namespace App\Http\Requests\Shift;

use App\Http\Requests\BaseQueryRequest;

class ShiftQueryRequest extends BaseQueryRequest
{
    protected function queryRules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:50'],
            'shift_date' => ['sometimes', 'date'],
            'shift_date_from' => ['sometimes', 'date'],
            'shift_date_to' => ['sometimes', 'date', 'after_or_equal:shift_date_from'],
            'start_time_from' => ['sometimes', 'date_format:H:i'],
            'start_time_to' => ['sometimes', 'date_format:H:i', 'after_or_equal:start_time_from'],
            'end_time_from' => ['sometimes', 'date_format:H:i'],
            'end_time_to' => ['sometimes', 'date_format:H:i', 'after_or_equal:end_time_from'],
        ];
    }

    public function filters(): array
    {
        return $this->safe()->only([
            'name',
            'shift_date',
            'shift_date_from',
            'shift_date_to',
            'start_time_from',
            'start_time_to',
            'end_time_from',
            'end_time_to',
        ]);
    }
}
