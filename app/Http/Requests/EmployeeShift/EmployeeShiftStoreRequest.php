<?php

namespace App\Http\Requests\EmployeeShift;

use App\Models\EmployeeShift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeShiftStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'string', 'exists:employees,id'],
            'shift_id' => ['required', 'string', 'exists:shifts,id'],
            'assigned_date' => ['required', 'date'],
            'status' => ['sometimes', 'integer', Rule::in([
                EmployeeShift::STATUS_SCHEDULED,
                EmployeeShift::STATUS_PRESENT,
                EmployeeShift::STATUS_LATE,
                EmployeeShift::STATUS_EARLY_LEAVE,
            ])],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
