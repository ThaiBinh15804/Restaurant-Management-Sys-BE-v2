<?php

namespace App\Http\Requests\Menu;

use App\Http\Requests\BaseQueryRequest;

class MenuQueryRequest extends BaseQueryRequest
{
    protected function queryRules(): array
    {
        return [
            'name'       => ['sometimes', 'string', 'max:255'],
            'desc'       => ['sometimes', 'string', 'max:255'],
            'is_active'  => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Trả về danh sách filter hợp lệ từ request query.
     */
    public function filters(): array
    {
        return [
            'name'       => $this->query('name', null),
            'desc'       => $this->query('desc', null),
            'is_active'  => $this->query('is_active', null),
        ];
    }
}
