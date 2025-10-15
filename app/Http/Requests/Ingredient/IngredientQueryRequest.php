<?php

namespace App\Http\Requests\Ingredient;

use App\Http\Requests\BaseQueryRequest;

class IngredientQueryRequest extends BaseQueryRequest
{
    /**
     * Additional query rules for ingredient filtering.
     */
    protected function queryRules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'unit' => ['sometimes', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
            'low_stock' => ['sometimes', 'boolean'], // Filter ingredients below min_stock
            'category_ids' => ['sometimes', 'array'], // Filter by multiple categories
            'category_ids.*' => ['string', 'max:10'], // Each category ID must be a string
        ];
    }

    /**
     * Get validated filters for query building.
     */
    public function filters(): array
    {
        return $this->only([
            'name',
            'unit',
            'is_active',
            'low_stock',
            'category_ids',
        ]);
    }
}
