<?php

namespace App\Http\Requests\Reservation;

use App\Http\Requests\BaseQueryRequest;

class ReservationQueryRequest extends BaseQueryRequest
{
    protected function queryRules(): array
    {
        return [
            'customer_name' => ['sometimes', 'string', 'max:255'],
            'customer_phone' => ['sometimes', 'string', 'max:11'],
            'reserved_at_from' => ['sometimes', 'date_format:Y-m-d H:i:s'],
            'reserved_at_to' => ['sometimes', 'date_format:Y-m-d H:i:s'],
        ];
    }

    public function filters(): array
    {
        return $this->safe()->only([
            'customer_name',
            'customer_phone',
            'reserved_at_from',
            'reserved_at_to',
        ]);
    }
}
