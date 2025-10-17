<?php

namespace App\Http\Requests\EmployeeShift;

use App\Models\EmployeeShift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAssignEmployeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shift_id' => ['required', 'string', 'exists:shifts,id'],
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['required', 'string', 'exists:employees,id', 'distinct'],
            'status' => ['sometimes', 'integer', Rule::in([
                EmployeeShift::STATUS_SCHEDULED,
                EmployeeShift::STATUS_PRESENT,
                EmployeeShift::STATUS_LATE,
                EmployeeShift::STATUS_EARLY_LEAVE,
            ])],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'shift_id.required' => 'Shift ID là bắt buộc',
            'shift_id.exists' => 'Shift không tồn tại',
            'employee_ids.required' => 'Danh sách nhân viên là bắt buộc',
            'employee_ids.array' => 'Danh sách nhân viên phải là một mảng',
            'employee_ids.min' => 'Cần ít nhất một nhân viên',
            'employee_ids.*.exists' => 'Một hoặc nhiều nhân viên không tồn tại',
            'employee_ids.*.distinct' => 'Danh sách nhân viên có ID trùng lặp',
            'status.integer' => 'Trạng thái phải là số nguyên',
            'notes.max' => 'Ghi chú không được vượt quá 500 ký tự',
        ];
    }
}
