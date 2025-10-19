<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->syncPermissions();
            $this->createRolesIfNotExist();
            $this->createRolePermissionsIfNotExist();
        });
    }

    /**
     * Sync permissions from config file to database
     */
    private function syncPermissions(): void
    {
        $permissionConfig = config('permissions.modules', []);
        
        foreach ($permissionConfig as $moduleKey => $moduleData) {
            $permissions = $moduleData['permissions'] ?? [];
            
            foreach ($permissions as $code => $permissionData) {
                Permission::updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $permissionData['name'],
                        'description' => $permissionData['description'],
                        'is_active' => true,
                    ]
                );
            }
        }

        // Optionally disable permissions not in config (if enabled in config)
        if (config('permissions.sync.auto_disable_unused', false)) {
            $configPermissionCodes = $this->getAllPermissionCodesFromConfig();
            Permission::whereNotIn('code', $configPermissionCodes)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }
    }

    /**
     * Create roles if they don't exist (no overwrite)
     */
    private function createRolesIfNotExist(): void
    {
        $roleConfig = config('permissions.roles', []);
        
        foreach ($roleConfig as $roleKey => $roleData) {
            Role::firstOrCreate(
                ['name' => $roleData['name']], // Search criteria
                [
                    'description' => $roleData['description'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Create role permissions if they don't exist (no overwrite)
     */
    private function createRolePermissionsIfNotExist(): void
    {
        $roleConfig = config('permissions.roles', []);
        
        foreach ($roleConfig as $roleKey => $roleData) {
            $role = Role::where('name', $roleData['name'])->first();
            
            if (!$role) {
                continue;
            }

            $permissionCodes = $roleData['permissions'];
            
            // Handle wildcard permissions (all permissions)
            if ($permissionCodes === '*') {
                $permissionCodes = $this->getAllPermissionCodesFromConfig();
            }

             $permissionIds = Permission::whereIn('code', $permissionCodes)->pluck('id');
            $role->permissions()->sync($permissionIds);
        }
    }

    /**
     * Get all permission codes from config
     */
    private function getAllPermissionCodesFromConfig(): array
    {
        $permissionConfig = config('permissions.modules', []);
        $allCodes = [];
        
        foreach ($permissionConfig as $moduleData) {
            $permissions = $moduleData['permissions'] ?? [];
            $allCodes = array_merge($allCodes, array_keys($permissions));
        }
        
        return $allCodes;
    }
}