<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends BaseModel
{
    protected $table = 'invoices';
    protected $idPrefix = 'INV';

    protected $fillable = [
        'table_session_id',
        'total_amount',
        'discount',
        'tax',
        'final_amount',
        'status',
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

    protected $appends = ['status_label'];

    // Relationships
    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class, 'table_session_id');
    }

    public function invoicePromotions(): HasMany
    {
        return $this->hasMany(InvoicePromotion::class, 'invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Accessors
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            0 => 'Unpaid',
            1 => 'Partially Paid',
            2 => 'Paid',
            3 => 'Cancelled',
            default => 'Unknown',
        };
    }

    // Scopes
    public function scopeUnpaid($query)
    {
        return $query->where('status', 0);
    }

    public function scopePaid($query)
    {
        return $query->where('status', 2);
    }
}