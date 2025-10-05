<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends BaseModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'payrolls';

    /**
     * ID prefix for custom ID generation.
     *
     * @var string
     */
    protected $idPrefix = 'PAY';

    /**
     * Payroll status constants.
     */
    public const STATUS_DRAFT = 0;
    public const STATUS_PAID = 1;
    public const STATUS_CANCELLED = 2;

    /**
     * Payment method constants.
     */
    public const PAYMENT_CASH = 0;
    public const PAYMENT_BANK_TRANSFER = 1;
    public const PAYMENT_CREDIT_CARD = 2;
    public const PAYMENT_E_WALLET = 3;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'month',
        'year',
        'base_salary',
        'bonus',
        'deductions',
        'final_salary',
        'status',
        'payment_method',
        'payment_ref',
        'paid_at',
        'notes',
        'paid_by',
        'employee_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'base_salary' => 'decimal:2',
        'bonus' => 'decimal:2',
        'deductions' => 'decimal:2',
        'final_salary' => 'decimal:2',
        'status' => 'integer',
        'payment_method' => 'integer',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'status_label',
        'payment_method_label',
    ];

    /**
     * Get the employee who owns the payroll record.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get the employee who processed the payroll.
     */
    public function paidByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'paid_by');
    }

    /**
     * Get the items associated with the payroll.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class, 'payroll_id');
    }

    /**
     * Human readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => 'Paid',
            self::STATUS_CANCELLED => 'Cancelled',
            default => 'Draft',
        };
    }

    /**
     * Human readable payment method label.
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            self::PAYMENT_BANK_TRANSFER => 'Bank Transfer',
            self::PAYMENT_CREDIT_CARD => 'Credit Card',
            self::PAYMENT_E_WALLET => 'E-Wallet',
            default => 'Cash',
        };
    }
}
