<?php

namespace App\Http\Requests\TableSession;

use App\Http\Requests\BaseQueryRequest;

class TableSessionQueryRequest extends BaseQueryRequest
{
    /**
     * Quy định các rule cho query params của TableSession.
     */
    protected function queryRules(): array
    {
        return [
            'is_active'       => ['sometimes', 'boolean'],
            'session_status'  => ['sometimes', 'string', 'in:empty,pending,active,paying,completed,cancelled'],
            'capacity'        => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * Trả về các filter hợp lệ từ query string.
     */
    public function filters(): array
    {
        return [
            'is_active'      => $this->query('is_active', null),
            'session_status' => $this->query('session_status', null),
            'capacity'       => $this->query('capacity', null),
        ];
    }
}
