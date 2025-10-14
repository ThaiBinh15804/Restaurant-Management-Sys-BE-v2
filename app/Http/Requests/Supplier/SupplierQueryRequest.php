<?php

namespace App\Http\Requests\Supplier;

use App\Http\Requests\BaseQueryRequest;

class SupplierQueryRequest extends BaseQueryRequest
{
    /**
     * Additional query rules for supplier filtering.
     */
    protected function queryRules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'string', 'email', 'max:100'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get validated filters for query building.
     */
    public function filters(): array
    {
        return $this->only([
            'name',
            'email',
            'phone',
            'is_active',
        ]);
    }
}
