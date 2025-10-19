<?php

namespace App\Http\Requests\EmployeeShift;

use App\Models\EmployeeShift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeShiftStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'integer', Rule::in([
                EmployeeShift::STATUS_SCHEDULED,
                EmployeeShift::STATUS_PRESENT,
                EmployeeShift::STATUS_LATE,
                EmployeeShift::STATUS_EARLY_LEAVE,
                EmployeeShift::STATUS_ABSENT,
            ])],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
