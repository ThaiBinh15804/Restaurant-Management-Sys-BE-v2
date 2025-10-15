<?php

namespace App\Models;


class Invoice extends BaseModel
{
    // Status constants
    const STATUS_UNPAID = 0;
    const STATUS_PARTIALLY_PAID = 1;
    const STATUS_PAID = 2;
    const STATUS_CANCELLED = 3;
    const STATUS_MERGED = 4; // 🔥 Trạng thái hóa đơn đã được gộp

    protected $table = 'invoices';
    protected $idPrefix = 'IN'; // Ví dụ: IN0001, IN0002,...

    protected $fillable = [
        'table_session_id',
        'total_amount',
        'discount',
        'tax',
        'final_amount',
        'status',
        'parent_invoice_id',   
        'merged_invoice_id',   
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
        return $query->where('status', self::STATUS_PAID);
    }

    /**
     * Scope: chỉ lấy các hóa đơn có thể gộp (Unpaid hoặc Partially Paid)
     */
    public function scopeMergeable($query)
    {
        return $query->whereIn('status', [self::STATUS_UNPAID, self::STATUS_PARTIALLY_PAID]);
    }

    /**
     * Quan hệ: Invoice cha (khi tách hóa đơn)
     */
    public function parentInvoice()
    {
        return $this->belongsTo(Invoice::class, 'parent_invoice_id');
    }

    /**
     * Quan hệ: Các invoice con (được tách từ invoice này)
     */
    public function childInvoices()
    {
        return $this->hasMany(Invoice::class, 'parent_invoice_id');
    }

    /**
     * Quan hệ: Invoice tổng (khi được gộp vào)
     */
    public function mergedInvoice()
    {
        return $this->belongsTo(Invoice::class, 'merged_invoice_id');
    }

    /**
     * Quan hệ: Các invoice đã được gộp vào invoice này
     */
    public function mergedFromInvoices()
    {
        return $this->hasMany(Invoice::class, 'merged_invoice_id');
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
            self::STATUS_UNPAID => 'Unpaid',
            self::STATUS_PARTIALLY_PAID => 'Partially Paid',
            self::STATUS_PAID => 'Paid',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_MERGED => 'Merged',
            default => 'Unknown',
        };
    }

    /**
     * Kiểm tra xem invoice có thể gộp không
     */
    public function canBeMerged(): bool
    {
        return in_array($this->status, [self::STATUS_UNPAID, self::STATUS_PARTIALLY_PAID]);
    }

    /**
     * Kiểm tra xem invoice có thể tách không
     */
    public function canBeSplit(): bool
    {
        return in_array($this->status, [self::STATUS_UNPAID, self::STATUS_PARTIALLY_PAID])
            && is_null($this->merged_invoice_id);
    }

    /**
     * Tính tổng số tiền đã thanh toán
     */
    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount');
    }

    /**
     * Tính số tiền còn phải trả
     */
    public function getRemainingAmountAttribute(): float
    {
        return max(0, $this->final_amount - $this->total_paid);
    }
}
