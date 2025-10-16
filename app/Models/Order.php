<?php

namespace App\Models;

class Order extends BaseModel
{
    protected $table = 'orders';
    protected $idPrefix = 'OR';

    // Status constants
    const STATUS_OPEN = 0;
    const STATUS_IN_PROGRESS = 1;
    const STATUS_SERVED = 2;
    const STATUS_PAID = 3;
    const STATUS_CANCELLED = 4;

    protected $fillable = [
        'table_session_id',
        'status',
        'total_amount',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'status' => 'integer',
        'total_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function tableSession()
    {
        return $this->belongsTo(TableSession::class, 'table_session_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }
}
