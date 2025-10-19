<?php

namespace App\Http\Requests\Report;

use Illuminate\Validation\Rule;

class RevenueReportRequest extends BaseReportRequest
{
    protected function additionalRules(): array
    {
        return [
            'group_by' => ['nullable', 'string', Rule::in(['day', 'week', 'month'])],
        ];
    }

    public function groupBy(): string
    {
        return $this->input('group_by', 'day');
    }
}
