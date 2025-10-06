<?php

namespace App\Http\Requests\Shift;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShiftUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $shiftId = $this->route('id') ?? $this->route('shift');

        return [
            'name' => ['sometimes', 'string', 'max:50'],
            'shift_date' => ['sometimes', 'nullable', 'date'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $start = $this->input('start_time');
            $end = $this->input('end_time');

            if ($start && $end && strtotime($end) <= strtotime($start)) {
                $validator->errors()->add('end_time', 'The end time must be after the start time.');
            }
        });
    }
}
