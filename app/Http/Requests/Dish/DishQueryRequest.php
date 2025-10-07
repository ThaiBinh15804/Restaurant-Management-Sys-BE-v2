<?php

namespace App\Http\Requests\Dish;

use App\Http\Requests\BaseQueryRequest;

class DishQueryRequest extends BaseQueryRequest
{
    /**
     * Các rules validate cho query params của Dish.
     */
    protected function queryRules(): array
    {
        return [
            'name'          => ['sometimes', 'string', 'max:255'],
            'is_active'     => ['sometimes', 'boolean'],
            'category'      => ['sometimes', 'string', 'exists:dish_categories,id'],
            'cooking_time'  => ['sometimes', 'integer', 'min:1'],
            'min_price'     => ['sometimes', 'numeric', 'min:0'],
            'max_price'     => ['sometimes', 'numeric', 'gte:min_price'], // phải >= min_price nếu có
        ];
    }

    /**
     * Lấy các field filter hợp lệ.
     */
    public function filters(): array
    {
        return $this->safe()->only([
            'name',
            'is_active',
            'category',
            'cooking_time',
            'min_price',
            'max_price',
        ]);
    }
}
