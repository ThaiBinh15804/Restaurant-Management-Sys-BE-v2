<?php

namespace App\Http\Requests\Report;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

abstract class BaseReportRequest extends FormRequest
{
    protected Carbon $resolvedStartDate;
    protected Carbon $resolvedEndDate;

    public function authorize(): bool
    {
        return true;
    }

    final public function rules(): array
    {
        return array_merge($this->dateRangeRules(), $this->additionalRules());
    }

    protected function dateRangeRules(): array
    {
        return [
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    protected function additionalRules(): array
    {
        return [];
    }

    protected function prepareForValidation(): void
    {
        $now = Carbon::now();
        $endInput = $this->input('end_date');
        $startInput = $this->input('start_date');

        if (!$endInput) {
            $this->merge(['end_date' => $now->toDateString()]);
        }

        if (!$startInput) {
            $defaultStart = $now->copy()->subDays(29);
            $this->merge(['start_date' => $defaultStart->toDateString()]);
        }
    }

    protected function passedValidation(): void
    {
        $start = Carbon::createFromFormat('Y-m-d', $this->validated()['start_date'])->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $this->validated()['end_date'])->endOfDay();

        if ($start->greaterThan($end)) {
            abort(422, 'The start_date must be before or equal to end_date.');
        }

        $this->resolvedStartDate = $start;
        $this->resolvedEndDate = $end;
    }

    public function dateRange(): array
    {
        return [$this->resolvedStartDate, $this->resolvedEndDate];
    }
}
