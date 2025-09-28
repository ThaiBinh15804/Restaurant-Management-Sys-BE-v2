<?php

namespace App\Models;

class TableSession extends BaseModel
{
    protected $table = 'table_sessions';
    protected $idPrefix = 'TS';

    protected $fillable = [
        'type',
        'status',
        'parent_session_id',
        'merged_into_session_id',
        'started_at',
        'ended_at',
        'customer_id',
        'employee_id',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'type' => 'integer',
        'status' => 'integer',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Quan hệ tự liên kết
    public function parentSession()
    {
        return $this->belongsTo(TableSession::class, 'parent_session_id');
    }

    public function mergedIntoSession()
    {
        return $this->belongsTo(TableSession::class, 'merged_into_session_id');
    }

    // Quan hệ với customer
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    // Quan hệ với employee
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    // Quan hệ pivot với reservations
    public function reservations()
    {
        return $this->belongsToMany(Reservation::class, 'table_session_reservations', 'table_session_id', 'reservation_id');
    }
}
