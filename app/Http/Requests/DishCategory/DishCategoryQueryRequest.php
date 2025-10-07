<?php

namespace App\Http\Requests\DishCategory;

use App\Http\Requests\BaseQueryRequest;

class DishCategoryQueryRequest extends BaseQueryRequest
{
    protected function queryRules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'desc' => ['sometimes', 'string', 'max:255'],
        ];
    }

    public function filters(): array
    {
        return $this->safe()->only([
            'name',
            'desc',
        ]);
    }
}
