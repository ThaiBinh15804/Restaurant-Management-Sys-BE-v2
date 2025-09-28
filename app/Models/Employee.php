<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends BaseModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'employees';

    /**
     * ID prefix for custom ID generation.
     *
     * @var string
     */
    protected $idPrefix = 'EMP';

    /**
     * Contract type constants.
     */
    public const CONTRACT_FULL_TIME = 0;
    public const CONTRACT_PART_TIME = 1;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'full_name',
        'phone',
        'gender',
        'address',
        'bank_account',
        'contract_type',
        'position',
        'base_salary',
        'hire_date',
        'is_active',
        'user_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'contract_type' => 'integer',
        'base_salary' => 'decimal:2',
        'hire_date' => 'date',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the employee profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the shifts assigned to the employee.
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(EmployeeShift::class, 'employee_id');
    }

    /**
     * Get the payroll records for the employee.
     */
    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class, 'employee_id');
    }

    /**
     * Payrolls processed by this employee.
     */
    public function processedPayrolls(): HasMany
    {
        return $this->hasMany(Payroll::class, 'paid_by');
    }

    /**
     * Scope a query to only include active employees.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Human readable contract label.
     */
    public function getContractLabelAttribute(): string
    {
        return match ($this->contract_type) {
            self::CONTRACT_PART_TIME => 'Part-time',
            default => 'Full-time',
        };
    }
}
