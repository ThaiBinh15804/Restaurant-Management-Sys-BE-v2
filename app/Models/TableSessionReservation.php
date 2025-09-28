<?php

namespace App\Models;

class TableSessionReservation extends BaseModel
{
    protected $table = 'table_session_reservations';
    protected $idPrefix = 'TSR';

    protected $fillable = [
        'table_session_id',
        'reservation_id',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Quan hệ với TableSession
    public function tableSession()
    {
        return $this->belongsTo(TableSession::class, 'table_session_id');
    }

    // Quan hệ với Reservation
    public function reservation()
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }
}
