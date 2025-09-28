<?php

namespace App\Models;

class TableSessionDiningTable extends BaseModel
{
    protected $table = 'table_session_dining_table';
    protected $idPrefix = 'TSDT';

    protected $fillable = [
        'table_session_id',
        'dining_table_id',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function tableSession()
    {
        return $this->belongsTo(TableSession::class, 'table_session_id');
    }

    public function diningTable()
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }
}
