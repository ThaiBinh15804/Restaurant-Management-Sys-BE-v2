<?php

namespace App\Models;

class Payment extends BaseModel
{
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
        return $query->where('status', 1);
    }

    /**
     * Scope: chỉ lấy các payment đang chờ xử lý
     */
    public function scopePending($query)
    {
        return $query->where('status', 0);
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
            0 => 'Pending',
            1 => 'Completed',
            2 => 'Failed',
            3 => 'Refunded',
            default => 'Unknown',
        };
    }

    /**
     * Trả về label phương thức thanh toán dạng text
     */
    public function getMethodLabelAttribute(): string
    {
        return match ($this->method) {
            0 => 'Cash',
            1 => 'Bank Transfer',
            default => 'Unknown',
        };
    }
}
