<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class StockExport extends BaseModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_exports';

    /**
     * ID prefix for custom ID generation.
     *
     * @var string
     */
    protected $idPrefix = 'SEX';

    /**
     * Status constants.
     */
    public const STATUS_DRAFT = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_COMPLETED = 2;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'export_date',
        'purpose',
        'status',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'export_date' => 'date',
        'status' => 'integer',
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
     * Get the details of this stock export.
     */
    public function details(): HasMany
    {
        return $this->hasMany(StockExportDetail::class, 'stock_export_id');
    }

    /**
     * Get the status label.
     *
     * @return string
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_COMPLETED => 'Completed',
            default => 'Unknown',
        };
    }

    /**
     * Check if the export is in draft status.
     *
     * @return bool
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if the export is approved.
     *
     * @return bool
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if the export is completed.
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
