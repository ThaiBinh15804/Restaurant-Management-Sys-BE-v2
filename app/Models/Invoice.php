<?php

namespace App\Models;


class Invoice extends BaseModel
{

    protected $table = 'invoices';
    protected $idPrefix = 'IN'; // Ví dụ: IN0001, IN0002,...

    protected $fillable = [
        'table_session_id',
        'total_amount',
        'discount',
        'tax',
        'final_amount',
        'status',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope: chỉ lấy các hóa đơn đã thanh toán
     */
    public function scopePaid($query)
    {
        return $query->where('status', 2);
    }

    /**
     * Quan hệ: Invoice có thể có nhiều payment
     */
    public function payments()
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }

    /**
     * Quan hệ: Invoice có thể có nhiều khuyến mãi áp dụng
     */
    public function invoicePromotions()
    {
        return $this->hasMany(InvoicePromotion::class, 'invoice_id');
    }

    /**
     * (Tùy chọn) Quan hệ với TableSession nếu bạn dùng bảng đó
     */
    public function tableSession()
    {
        return $this->belongsTo(TableSession::class, 'table_session_id');
    }

    /**
     * Kiểm tra trạng thái thanh toán dạng text
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            0 => 'Unpaid',
            1 => 'Partially Paid',
            2 => 'Paid',
            3 => 'Cancelled',
            default => 'Unknown',
        };
    }
}
