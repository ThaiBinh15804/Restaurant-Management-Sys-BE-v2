<?php

namespace App\Models;

class Payment extends BaseModel
{
    // Status constants
    const STATUS_PENDING = 0;
    const STATUS_COMPLETED = 1;
    const STATUS_FAILED = 2;
    const STATUS_REFUNDED = 3;

    // Method constants
    const METHOD_CASH = 0;
    const METHOD_BANK_TRANSFER = 1;

    protected $table = 'payments';
    protected $idPrefix = 'PM'; // Ví dụ: PM0001, PM0002,...

    protected $fillable = [
        'amount',
        'method',
        'status',
        'paid_at',
        'invoice_id',
        'employee_id',
        'desc_issue',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'method' => 'integer',
        'status' => 'integer',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope: chỉ lấy các payment đã hoàn thành
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope: chỉ lấy các payment đang chờ xử lý
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Quan hệ: Payment thuộc về 1 Invoice
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * Quan hệ: Payment thực hiện bởi 1 Employee (nếu có)
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Trả về label trạng thái thanh toán dạng text
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_REFUNDED => 'Refunded',
            default => 'Unknown',
        };
    }

    /**
     * Trả về label phương thức thanh toán dạng text
     */
    public function getMethodLabelAttribute(): string
    {
        return match ($this->method) {
            self::METHOD_CASH => 'Cash',
            self::METHOD_BANK_TRANSFER => 'Bank Transfer',
            default => 'Unknown',
        };
    }
}
