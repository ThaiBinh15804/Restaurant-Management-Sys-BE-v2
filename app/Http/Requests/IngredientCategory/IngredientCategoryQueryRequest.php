<?php

namespace App\Http\Requests\IngredientCategory;

use App\Http\Requests\BaseQueryRequest;

class IngredientCategoryQueryRequest extends BaseQueryRequest
{
    /**
     * Get the query-specific validation rules.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function queryRules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ];
    }

    /**
     * Apply filters to the query builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function applyFilters($query)
    {
        // Apply search filter
        if ($this->filled('search')) {
            $search = $this->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        // Apply active status filter
        if ($this->has('is_active')) {
            $query->where('is_active', $this->boolean('is_active'));
        }

        return $query;
    }
}
