<?php

namespace App\Models;

use App\Models\BaseAuthenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends BaseAuthenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * ID prefix for custom ID generation.
     *
     * @var string
     */
    protected $idPrefix = 'U';

    /**
     * User status constants.
     */
    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_PENDING = 2;
    public const STATUS_BANNED = 3;
    public const STATUS_DELETED = 4;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'password',
        'status',
        'avatar',
        'role_id',
        'email_verified_at',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'created_by',
        'updated_by',
    ];

    protected $appends = [
        'status_label',
        'name',
    ];

    protected $with = [
        'role',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the role that owns the user.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Get the refresh tokens for the user.
     */
    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class, 'user_id');
    }

    /**
     * Get the customer profile linked to the user.
     */
    public function customerProfile(): HasOne
    {
        return $this->hasOne(Customer::class, 'user_id');
    }

    /**
     * Get the employee profile linked to the user.
     */
    public function employeeProfile(): HasOne
    {
        return $this->hasOne(Employee::class, 'user_id');
    }

    /**
     * Get the user who created this record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this record.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Check if user has a specific permission.
     *
     * @param string $permissionCode
     * @return bool
     */
    public function hasPermission(string $permissionCode): bool
    {
        if (!$this->role) {
            return false;
        }

        return $this->role->hasPermission($permissionCode);
    }

    /**
     * Check if user has any of the given permissions.
     *
     * @param array $permissions
     * @return bool
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the given permissions.
     *
     * @param array $permissions
     * @return bool
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all permissions for the user.
     *
     * @return array
     */
    public function getAllPermissions(): array
    {
        if (!$this->role) {
            return [];
        }

        return $this->role->permissions()
            ->where('is_active', true)
            ->pluck('code')
            ->toArray();
    }

    /**
     * Get all permission objects for the user.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPermissions()
    {
        if (!$this->role) {
            return collect();
        }

        return $this->role->permissions()
            ->where('is_active', true)
            ->get();
    }

    /**
     * Check if user is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if user is a customer.
     *
     * @return bool
     */
    public function isCustomer(): bool
    {
        return $this->customerProfile()->exists();
    }

    /**
     * Check if user is an employee.
     *
     * @return bool
     */
    public function isEmployee(): bool
    {
        return $this->employeeProfile()->exists();
    }

    /**
     * Get user type.
     *
     * @return string|null Returns 'customer', 'employee', or null if neither
     */
    public function getUserType(): ?string
    {
        if ($this->relationLoaded('customerProfile') && $this->customerProfile) {
            return 'customer';
        }
        
        if ($this->relationLoaded('employeeProfile') && $this->employeeProfile) {
            return 'employee';
        }

        if ($this->customerProfile()->exists()) {
            return 'customer';
        }
        
        if ($this->employeeProfile()->exists()) {
            return 'employee';
        }
        
        return null;
    }

    /**
     * Get the profile instance (customer or employee).
     *
     * @return \App\Models\Customer|\App\Models\Employee|null
     */
    public function getProfile()
    {
        if ($this->isCustomer()) {
            return $this->customerProfile;
        }
        
        if ($this->isEmployee()) {
            return $this->employeeProfile;
        }
        
        return null;
    }

    /**
     * Scope a query to only include active users.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope a query to only include users with specific role.
     */
    public function scopeWithRole($query, string $roleId)
    {
        return $query->where('role_id', $roleId);
    }

    /**
     * Get status label.
     *
     * @return string
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_PENDING => 'Pending',
            self::STATUS_BANNED => 'Banned',
            self::STATUS_DELETED => 'Deleted',
            default => 'Unknown',
        };
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [
            'email' => $this->email,
            'role_id' => $this->role_id,
            'status' => $this->status,
        ];
    }

    /**
     * Get the name based on related profile (customer or employee).
     *
     * @return string|null
     */
    public function getNameAttribute(): ?string
    {
        if ($this->relationLoaded('customerProfile') && $this->customerProfile) {
            return $this->customerProfile->full_name ?? null;
        }

        if ($this->relationLoaded('employeeProfile') && $this->employeeProfile) {
            return $this->employeeProfile->full_name ?? null;
        }

        if ($this->customerProfile()->exists()) {
            return $this->customerProfile->full_name ?? null;
        }

        if ($this->employeeProfile()->exists()) {
            return $this->employeeProfile->full_name ?? null;
        }

        return null;
    }
}
