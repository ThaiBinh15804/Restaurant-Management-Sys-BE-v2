<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePromotion extends BaseModel
{
    protected $table = 'invoice_promotions';
    protected $idPrefix = 'INVP';

    protected $fillable = [
        'invoice_id',
        'promotion_id',
        'applied_at',
        'discount_value',
        'created_by',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'discount_value' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'promotion_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}