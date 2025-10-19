<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePromotion extends BaseModel
{

    protected $table = 'invoice_promotions';
    protected $idPrefix = 'IP'; // Ví dụ: IP0001, IP0002,...

    protected $fillable = [
        'applied_at',
        'discount_value',
        'promotion_id',
        'invoice_id',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'discount_value' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class, 'promotion_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
