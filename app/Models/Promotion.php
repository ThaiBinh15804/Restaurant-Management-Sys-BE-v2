<?php

namespace App\Models;


class Promotion extends BaseModel
{
    protected $table = 'promotions';
    protected $idPrefix = 'PR'; // ví dụ: PR0001, PR0002,...

    protected $fillable = [
        'code',
        'description',
        'discount_percent',
        'start_date',
        'end_date',
        'usage_limit',
        'is_active',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'discount_percent' => 'decimal:2',
        'usage_limit' => 'integer',
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope: chỉ lấy các promotion đang hoạt động
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Quan hệ: một promotion có thể được áp dụng cho nhiều hóa đơn
     */
    public function invoicePromotions()
    {
        return $this->hasMany(InvoicePromotion::class, 'promotion_id');
    }

    /**
     * Kiểm tra xem promotion hiện có hiệu lực không (theo ngày)
     */
    public function isCurrentlyValid(): bool
    {
        $today = now()->toDateString();
        return $this->is_active && $this->start_date <= $today && $this->end_date >= $today;
    }
}
