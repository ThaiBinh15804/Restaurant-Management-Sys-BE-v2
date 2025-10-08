<?php

namespace App\Models;

class OrderItem extends BaseModel
{
    protected $table = 'order_items';
    protected $idPrefix = 'OI';

    protected $fillable = [
        'order_id',
        'dish_id',
        'quantity',
        'price',
        'total_price',
        'status',
        'notes',
        'prepared_by',
        'served_at',
        'cancelled_reason',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'status' => 'integer',
        'served_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function dish()
    {
        return $this->belongsTo(Dish::class, 'dish_id');
    }

    public function preparedBy()
    {
        return $this->belongsTo(Employee::class, 'prepared_by');
    }
}
