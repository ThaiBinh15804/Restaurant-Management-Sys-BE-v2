<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class EmailVerificationToken extends BaseModel
{
    /**
     * The table associated with the model.
     */
    protected $table = 'email_verification_tokens';

    /**
     * ID prefix for custom ID generation.
     */
    protected $idPrefix = 'EVT';

    /**
     * Token expiry time in minutes.
     */
    const EXPIRY_MINUTES = 60; // 1 hour

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'email',
        'token',
        'expires_at',
        'is_used',
        'ip_address',
        'user_agent',
        'temp_name',
        'temp_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'token',
        'temp_password',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_used' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Generate a new verification token.
     */
    public static function generateToken(): string
    {
        return hash('sha256', uniqid(mt_rand(), true));
    }

    /**
     * Create a new verification token record.
     */
    public static function createForRegistration(
        string $email,
        string $name,
        string $password,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        return self::create([
            'email' => $email,
            'token' => self::generateToken(),
            'expires_at' => Carbon::now()->addMinutes(self::EXPIRY_MINUTES),
            'temp_name' => $name,
            'temp_password' => Hash::make($password),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Find a valid verification token.
     */
    public static function findValidToken(string $token): ?self
    {
        return self::where('token', $token)
            ->where('expires_at', '>', Carbon::now())
            ->where('is_used', false)
            ->first();
    }

    /**
     * Mark token as used.
     */
    public function markAsUsed(): void
    {
        $this->update(['is_used' => true]);
    }

    /**
     * Check if token is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if token is valid (not used and not expired).
     */
    public function isValid(): bool
    {
        return !$this->is_used && !$this->isExpired();
    }

    /**
     * Scope to get active tokens.
     */
    public function scopeActive($query)
    {
        return $query->where('is_used', false)
            ->where('expires_at', '>', Carbon::now());
    }

    /**
     * Scope to get expired tokens.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', Carbon::now());
    }

    /**
     * Scope to get tokens by email.
     */
    public function scopeByEmail($query, string $email)
    {
        return $query->where('email', $email);
    }
}
