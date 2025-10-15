<?php

namespace App\Models;

class TableSession extends BaseModel
{
    // Type constants
    const TYPE_OFFLINE = 0;    
    const TYPE_MERGE = 1;      
    const TYPE_RESERVATION = 2; 
    const TYPE_SPLIT = 3;      

    // Status constants
    const STATUS_PENDING = 0;  
    const STATUS_ACTIVE = 1;   
    const STATUS_COMPLETED = 2;
    const STATUS_CANCELLED = 3;
    const STATUS_MERGED = 4;   

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

    // Quan hệ với dining tables (many-to-many)
    public function diningTables()
    {
        return $this->belongsToMany(
            DiningTable::class,
            'table_session_dining_table',
            'table_session_id',
            'dining_table_id'
        )->withTimestamps();
    }

    // Quan hệ với orders
    public function orders()
    {
        return $this->hasMany(Order::class, 'table_session_id');
    }

    // Quan hệ với invoices
    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'table_session_id');
    }

    /**
     * Scope: chỉ lấy session đang hoạt động
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope: chỉ lấy session có thể gộp (Pending hoặc Active)
     */
    public function scopeMergeable($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_ACTIVE])
            ->whereNull('merged_into_session_id');
    }

    /**
     * Kiểm tra xem session có thể gộp không
     */
    public function canBeMerged(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_ACTIVE])
            && is_null($this->merged_into_session_id);
    }

    /**
     * Lấy label cho type
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_OFFLINE => 'Offline',
            self::TYPE_MERGE => 'Merge',
            self::TYPE_RESERVATION => 'Reservation',
            self::TYPE_SPLIT => 'Split',
            default => 'Unknown',
        };
    }

    /**
     * Lấy label cho status
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_MERGED => 'Merged',
            default => 'Unknown',
        };
    }
}
