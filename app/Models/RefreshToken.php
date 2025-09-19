<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefreshToken extends BaseModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'refresh_tokens';

    /**
     * ID prefix for custom ID generation.
     *
     * @var string
     */
    protected $idPrefix = 'RT';

    /**
     * Token status constants.
     */
    const STATUS_ACTIVE = 1;
    const STATUS_EXPIRED = 0;
    const STATUS_REVOKED = 2;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'token',
        'expire_at',
        'status',
        'revoked_at',
        'revoked_by',
        'ip_address',
        'user_agent',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'token',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expire_at' => 'datetime',
            'revoked_at' => 'datetime',
            'status' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the refresh token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who revoked this token.
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /**
     * Check if the token is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE 
            && $this->expire_at > now()
            && is_null($this->revoked_at);
    }

    /**
     * Check if the token is expired.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expire_at <= now() || $this->status === self::STATUS_EXPIRED;
    }

    /**
     * Check if the token is revoked.
     *
     * @return bool
     */
    public function isRevoked(): bool
    {
        return !is_null($this->revoked_at) || $this->status === self::STATUS_REVOKED;
    }

    /**
     * Revoke the token.
     *
     * @param string|null $revokedBy
     * @return bool
     */
    public function revoke(?string $revokedBy = null): bool
    {
        $this->status = self::STATUS_REVOKED;
        $this->revoked_at = now();
        
        if ($revokedBy) {
            $this->revoked_by = $revokedBy;
        }

        return $this->save();
    }

    /**
     * Scope a query to only include active tokens.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
                    ->where('expire_at', '>', now())
                    ->whereNull('revoked_at');
    }

    /**
     * Scope a query to only include expired tokens.
     */
    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('expire_at', '<=', now())
              ->orWhere('status', self::STATUS_EXPIRED);
        });
    }

    /**
     * Scope a query to only include revoked tokens.
     */
    public function scopeRevoked($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('revoked_at')
              ->orWhere('status', self::STATUS_REVOKED);
        });
    }

    /**
     * Scope a query to only include tokens for a specific user.
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get status label.
     *
     * @return string
     */
    public function getStatusLabel(): string
    {
        if ($this->isRevoked()) {
            return 'Revoked';
        }
        
        if ($this->isExpired()) {
            return 'Expired';
        }
        
        if ($this->isActive()) {
            return 'Active';
        }
        
        return 'Unknown';
    }

    /**
     * Generate a new refresh token.
     *
     * @return string
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(64));
    }
}