<?php

namespace App\Http\Requests\TableSession;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MergeTablesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization được xử lý qua middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'source_session_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'source_session_ids.*' => [
                'required',
                'string',
                'exists:table_sessions,id',
                'distinct', // Không cho phép trùng lặp
            ],
            'target_session_id' => [
                'required',
                'string',
                'exists:table_sessions,id',
                // Không được trùng với các source sessions
                function ($attribute, $value, $fail) {
                    if (in_array($value, $this->input('source_session_ids', []))) {
                        $fail('Target session cannot be in the list of source sessions.');
                    }
                },
            ],
            'employee_id' => [
                'required',
                'string',
                'exists:employees,id',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_session_ids.required' => 'Source sessions are required',
            'source_session_ids.array' => 'Source sessions must be an array',
            'source_session_ids.min' => 'At least one source session is required',
            'source_session_ids.*.exists' => 'One or more source sessions do not exist',
            'source_session_ids.*.distinct' => 'Source sessions must be unique',
            'target_session_id.required' => 'Target session is required',
            'target_session_id.exists' => 'Target session does not exist',
            'employee_id.required' => 'Employee ID is required',
            'employee_id.exists' => 'Employee does not exist',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'source_session_ids' => 'source sessions',
            'target_session_id' => 'target session',
            'employee_id' => 'employee',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Additional custom validation logic if needed
            $sourceIds = $this->input('source_session_ids', []);
            $targetId = $this->input('target_session_id');

            // Ensure we have unique sessions
            if (count($sourceIds) !== count(array_unique($sourceIds))) {
                $validator->errors()->add(
                    'source_session_ids',
                    'Source sessions contain duplicate values'
                );
            }
        });
    }
}
