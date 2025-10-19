<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeShift extends BaseModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'employee_shifts';

    /**
     * ID prefix for custom ID generation.
     *
     * @var string
     */
    protected $idPrefix = 'ES';

    /**
     * Status constants.
     */
    public const STATUS_SCHEDULED = 0;
    public const STATUS_PRESENT = 1;
    public const STATUS_LATE = 2;
    public const STATUS_EARLY_LEAVE = 3;
    public const STATUS_ABSENT = 4;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'status',
        'check_in',
        'check_out',
        'overtime_hours',
        'notes',
        'employee_id',
        'shift_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'integer',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'overtime_hours' => 'integer',
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
    ];

    /**
     * Get the employee associated with the assignment.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Get the shift associated with the assignment.
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    /**
     * Human readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PRESENT => 'Present',
            self::STATUS_LATE => 'Late',
            self::STATUS_EARLY_LEAVE => 'Early Leave',
            self::STATUS_ABSENT => 'Absent',
            default => 'Scheduled',
        };
    }
}
