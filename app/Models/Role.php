<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends BaseModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'roles';

    /**
     * ID prefix for custom ID generation.
     *
     * @var string
     */
    protected $idPrefix = 'R';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the permissions that belong to the role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permission',
            'role_id',
            'permission_id'
        )->using(RolePermission::class)
         ->withTimestamps();
    }

    /**
     * Get the users for the role.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }

    /**
     * Check if role has a specific permission.
     *
     * @param string $permissionCode
     * @return bool
     */
    public function hasPermission(string $permissionCode): bool
    {
        $cacheKey = "role_permissions_{$this->id}";
        
        $permissionCodes = cache()->remember($cacheKey, 3600, function () {
            return $this->permissions()
                ->where('is_active', true)
                ->pluck('code')
                ->toArray();
        });

        return in_array($permissionCode, $permissionCodes);
    }

    /**
     * Check if role has any of the given permissions.
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
     * Check if role has all of the given permissions.
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
     * Sync permissions to role.
     *
     * @param array $permissionIds
     * @return void
     */
    public function syncPermissions(array $permissionIds): void
    {
        $this->permissions()->sync($permissionIds);
        $this->clearPermissionCache();
    }

    /**
     * Add permissions to role.
     *
     * @param array $permissionIds
     * @return void
     */
    public function addPermissions(array $permissionIds): void
    {
        $this->permissions()->attach($permissionIds);
        $this->clearPermissionCache();
    }

    /**
     * Remove permissions from role.
     *
     * @param array $permissionIds
     * @return void
     */
    public function removePermissions(array $permissionIds): void
    {
        $this->permissions()->detach($permissionIds);
        $this->clearPermissionCache();
    }

    /**
     * Clear permission cache for this role.
     *
     * @return void
     */
    public function clearPermissionCache(): void
    {
        cache()->forget("role_permissions_{$this->id}");
    }

    /**
     * Get all fillable attributes including parent.
     *
     * @return array
     */
    public function getFillable()
    {
        return array_merge(parent::getFillable(), $this->fillable);
    }
}