<?php

namespace App\Http\Requests\Report;

class TopDishReportRequest extends BaseReportRequest
{
    protected function additionalRules(): array
    {
        return [
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function limit(): int
    {
        return (int) $this->input('limit', 5);
    }
}
