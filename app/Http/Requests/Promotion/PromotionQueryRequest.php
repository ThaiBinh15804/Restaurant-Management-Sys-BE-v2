<?php

namespace App\Http\Requests\Promotion;

use App\Http\Requests\BaseQueryRequest;

class PromotionQueryRequest extends BaseQueryRequest
{
    /**
     * Các rules validate cho query params của Dish.
     */
    protected function queryRules(): array
    {
        return [
            'code'     => ['sometimes', 'string', 'max:255'],
            'desc'     => ['sometimes', 'string', 'max:255'],
            'is_active'     => ['sometimes', 'boolean'],
            'discount_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'], // mới
        ];
    }

    /**
     * Lấy các field filter hợp lệ.
     */
    public function filters(): array
    {
        return $this->safe()->only([
            'code',
            'desc',
            'is_active',
            'discount_percent',
        ]);
    }
}
