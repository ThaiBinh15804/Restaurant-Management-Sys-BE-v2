<?php

namespace App\Http\Requests\Stock;

use App\Http\Requests\BaseQueryRequest;
use App\Models\StockExport;
use Illuminate\Validation\Rule;

class StockQueryRequest extends BaseQueryRequest
{
    /**
     * Additional query rules for stock filtering.
     */
    protected function queryRules(): array
    {
        return [
            'type' => ['sometimes', 'string', Rule::in(['import', 'export', 'loss'])],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'supplier_id' => ['sometimes', 'string', 'exists:suppliers,id'],
            'ingredient_id' => ['sometimes', 'string', 'exists:ingredients,id'],
            'status' => ['sometimes', 'integer', Rule::in([
                StockExport::STATUS_DRAFT,
                StockExport::STATUS_APPROVED,
                StockExport::STATUS_COMPLETED,
            ])],
        ];
    }

    /**
     * Get validated filters for query building.
     */
    public function filters(): array
    {
        return $this->only([
            'type',
            'date_from',
            'date_to',
            'supplier_id',
            'ingredient_id',
            'status',
        ]);
    }
}
