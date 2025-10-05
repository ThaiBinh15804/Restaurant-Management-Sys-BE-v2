<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends BaseModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'customers';

    /**
     * ID prefix for custom ID generation.
     *
     * @var string
     */
    protected $idPrefix = 'CU';

    /**
     * Membership level constants.
     */
    public const MEMBERSHIP_BRONZE = 0;
    public const MEMBERSHIP_SILVER = 1;
    public const MEMBERSHIP_GOLD = 2;
    public const MEMBERSHIP_TITANIUM = 3;

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
        'membership_level',
        'user_id',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'membership_level' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'membership_label',
    ];

    /**
     * Get the user associated with the customer profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get a human readable label for the membership level.
     */
    public function getMembershipLabelAttribute(): string
    {
        return match ($this->membership_level) {
            self::MEMBERSHIP_SILVER => 'Silver',
            self::MEMBERSHIP_GOLD => 'Gold',
            self::MEMBERSHIP_TITANIUM => 'Titanium',
            default => 'Bronze',
        };
    }
}
