<?php

namespace App\Http\Requests\Reservation;

use App\Http\Requests\BaseQueryRequest;

class ReservationQueryRequest extends BaseQueryRequest
{
    protected function queryRules(): array
    {
        return [
            'status'  => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Trả về danh sách filter hợp lệ từ request query.
     */
    public function filters(): array
    {
        return [
            'status'  => $this->query('is_active', null),
        ];
    }
}
