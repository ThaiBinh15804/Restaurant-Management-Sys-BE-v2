<?php

namespace App\Http\Requests\EmployeeShift;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeShiftCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'check_in' => ['sometimes', 'date_format:Y-m-d H:i:s'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
