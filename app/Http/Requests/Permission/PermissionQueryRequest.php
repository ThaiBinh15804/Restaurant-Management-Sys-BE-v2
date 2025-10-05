<?php

namespace App\Http\Requests\Permission;

use App\Http\Requests\BaseQueryRequest;

class PermissionQueryRequest extends BaseQueryRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the query-specific validation rules.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function queryRules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
            'search' => 'sometimes|string|max:255',
        ];
    }

    /**
     * Get custom filter parameters.
     *
     * @return array
     */
    public function filters(): array
    {
        return $this->only([
            'name',
            'code',
            'is_active',
            'search',
        ]);
    }
}
