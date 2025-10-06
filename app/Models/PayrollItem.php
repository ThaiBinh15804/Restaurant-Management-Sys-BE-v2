<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends BaseModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'payroll_items';

    /**
     * ID prefix for custom ID generation.
     *
     * @var string
     */
    protected $idPrefix = 'PI';

    /**
     * The desired length for the generated ID.
     *
     * @var int
     */
    protected $idLength = 20;

    /**
     * Item type constants.
     */
    public const TYPE_EARNING = 0;
    public const TYPE_DEDUCTION = 1;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'payroll_id',
        'item_type',
        'code',
        'description',
        'amount',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'item_type' => 'integer',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'signed_amount',
    ];  

    /**
     * Get the payroll that owns the item.
     */
    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, 'payroll_id');
    }

    /**
     * Get the signed amount (positive for earnings, negative for deductions).
     */
    public function getSignedAmountAttribute(): string
    {
        $multiplier = $this->item_type === self::TYPE_DEDUCTION ? -1 : 1;
        return (string) ($this->amount * $multiplier);
    }
}
