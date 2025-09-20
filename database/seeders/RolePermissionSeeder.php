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
            $permissions = $this->createPermissions();
            
            $roles = $this->createRoles();
            
            $this->assignPermissionsToRoles($roles, $permissions);
        });
    }

    private function createPermissions(): array
    {
        $permissionGroups = [
            'users' => [
                'users.view' => 'View users',
                'users.create' => 'Create users',
                'users.edit' => 'Edit users',
                'users.delete' => 'Delete users',
                'users.manage_roles' => 'Manage user roles',
            ],
            
            'roles' => [
                'roles.view' => 'View roles',
                'roles.create' => 'Create roles',
                'roles.edit' => 'Edit roles',
                'roles.delete' => 'Delete roles',
                'roles.manage_permissions' => 'Manage role permissions',
            ],
            
            'permissions' => [
                'permissions.view' => 'View permissions',
                'permissions.create' => 'Create permissions',
                'permissions.edit' => 'Edit permissions',
                'permissions.delete' => 'Delete permissions',
            ],
            
            'categories' => [
                'categories.view' => 'View menu categories',
                'categories.create' => 'Create menu categories',
                'categories.edit' => 'Edit menu categories',
                'categories.delete' => 'Delete menu categories',
            ],
            
            'products' => [
                'products.view' => 'View products/menu items',
                'products.create' => 'Create products/menu items',
                'products.edit' => 'Edit products/menu items',
                'products.delete' => 'Delete products/menu items',
                'products.manage_pricing' => 'Manage product pricing',
            ],
            
            'orders' => [
                'orders.view' => 'View orders',
                'orders.create' => 'Create orders',
                'orders.edit' => 'Edit orders',
                'orders.delete' => 'Cancel/Delete orders',
                'orders.process' => 'Process orders',
                'orders.complete' => 'Complete orders',
                'orders.refund' => 'Refund orders',
            ],
            
            'tables' => [
                'tables.view' => 'View tables',
                'tables.create' => 'Create tables',
                'tables.edit' => 'Edit tables',
                'tables.delete' => 'Delete tables',
                'tables.manage_status' => 'Manage table status',
            ],
            
            'reservations' => [
                'reservations.view' => 'View reservations',
                'reservations.create' => 'Create reservations',
                'reservations.edit' => 'Edit reservations',
                'reservations.delete' => 'Cancel reservations',
                'reservations.confirm' => 'Confirm reservations',
            ],
            
            'inventory' => [
                'inventory.view' => 'View inventory',
                'inventory.create' => 'Create inventory items',
                'inventory.edit' => 'Edit inventory',
                'inventory.delete' => 'Delete inventory items',
                'inventory.adjust' => 'Adjust inventory levels',
                'inventory.reports' => 'View inventory reports',
            ],
            
            'reports' => [
                'reports.sales' => 'View sales reports',
                'reports.inventory' => 'View inventory reports',
                'reports.financial' => 'View financial reports',
                'reports.customer' => 'View customer reports',
                'reports.export' => 'Export reports',
            ],
            
            'system' => [
                'system.settings' => 'Manage system settings',
                'system.logs' => 'View system logs',
                'system.backup' => 'Perform system backup',
                'system.maintenance' => 'Perform system maintenance',
            ]
        ];

        $permissions = [];
        
        foreach ($permissionGroups as $group => $groupPermissions) {
            foreach ($groupPermissions as $code => $name) {
                $permission = Permission::create([
                    'code' => $code,
                    'name' => $name,
                    'description' => "Permission to {$name} in {$group} module",
                    'is_active' => true,
                ]);
                
                $permissions[$code] = $permission;
            }
        }

        return $permissions;
    }
   
    private function createRoles(): array
    {
        $rolesData = [
            'super_admin' => [
                'name' => 'Super Administrator',
                'description' => 'Full system access with all permissions',
            ],
            'admin' => [
                'name' => 'Administrator', 
                'description' => 'Administrative access with most permissions',
            ],
            'manager' => [
                'name' => 'Manager',
                'description' => 'Restaurant manager with operational permissions',
            ],
            'staff' => [
                'name' => 'Staff',
                'description' => 'Restaurant staff with limited permissions',
            ],
            'cashier' => [
                'name' => 'Cashier',
                'description' => 'Point of sale and order processing permissions',
            ],
            'kitchen' => [
                'name' => 'Kitchen Staff',
                'description' => 'Kitchen operations and order management',
            ],
            'waiter' => [
                'name' => 'Waiter/Server',
                'description' => 'Order taking and table service permissions',
            ],
        ];

        $roles = [];
        
        foreach ($rolesData as $key => $roleData) {
            $role = Role::create([
                'name' => $roleData['name'],
                'description' => $roleData['description'],
                'is_active' => true,
            ]);
            
            $roles[$key] = $role;
        }

        return $roles;
    }


    private function assignPermissionsToRoles(array $roles, array $permissions): void
    {
        $superAdminPermissions = array_keys($permissions);
        $this->attachPermissionsToRole($roles['super_admin'], $permissions, $superAdminPermissions);

        $adminPermissions = [
            'users.view', 'users.create', 'users.edit', 'users.manage_roles',
            'roles.view', 'roles.create', 'roles.edit', 'roles.manage_permissions',
            'permissions.view',
            'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
            'products.view', 'products.create', 'products.edit', 'products.delete', 'products.manage_pricing',
            'orders.view', 'orders.create', 'orders.edit', 'orders.delete', 'orders.process', 'orders.complete', 'orders.refund',
            'tables.view', 'tables.create', 'tables.edit', 'tables.delete', 'tables.manage_status',
            'reservations.view', 'reservations.create', 'reservations.edit', 'reservations.delete', 'reservations.confirm',
            'inventory.view', 'inventory.create', 'inventory.edit', 'inventory.delete', 'inventory.adjust', 'inventory.reports',
            'reports.sales', 'reports.inventory', 'reports.financial', 'reports.customer', 'reports.export',
            'system.settings', 'system.logs',
        ];
        $this->attachPermissionsToRole($roles['admin'], $permissions, $adminPermissions);

        $managerPermissions = [
            'users.view', 'users.create', 'users.edit',
            'roles.view',
            'categories.view', 'categories.create', 'categories.edit',
            'products.view', 'products.create', 'products.edit', 'products.manage_pricing',
            'orders.view', 'orders.create', 'orders.edit', 'orders.process', 'orders.complete', 'orders.refund',
            'tables.view', 'tables.create', 'tables.edit', 'tables.manage_status',
            'reservations.view', 'reservations.create', 'reservations.edit', 'reservations.delete', 'reservations.confirm',
            'inventory.view', 'inventory.edit', 'inventory.adjust', 'inventory.reports',
            'reports.sales', 'reports.inventory', 'reports.financial', 'reports.customer',
        ];
        $this->attachPermissionsToRole($roles['manager'], $permissions, $managerPermissions);

        $staffPermissions = [
            'categories.view',
            'products.view',
            'orders.view', 'orders.create', 'orders.edit',
            'tables.view', 'tables.manage_status',
            'reservations.view', 'reservations.create', 'reservations.edit',
            'inventory.view',
        ];
        $this->attachPermissionsToRole($roles['staff'], $permissions, $staffPermissions);

        $cashierPermissions = [
            'categories.view',
            'products.view',
            'orders.view', 'orders.create', 'orders.edit', 'orders.process', 'orders.complete',
            'tables.view', 'tables.manage_status',
            'reservations.view',
            'reports.sales',
        ];
        $this->attachPermissionsToRole($roles['cashier'], $permissions, $cashierPermissions);

        $kitchenPermissions = [
            'products.view',
            'orders.view', 'orders.process', 'orders.complete',
            'inventory.view', 'inventory.adjust',
        ];
        $this->attachPermissionsToRole($roles['kitchen'], $permissions, $kitchenPermissions);

        $waiterPermissions = [
            'categories.view',
            'products.view',
            'orders.view', 'orders.create', 'orders.edit',
            'tables.view', 'tables.manage_status',
            'reservations.view', 'reservations.create', 'reservations.edit',
        ];
        $this->attachPermissionsToRole($roles['waiter'], $permissions, $waiterPermissions);
    }

    /**
     * Attach permissions to a role using the custom pivot model
     */
    private function attachPermissionsToRole(Role $role, array $permissions, array $permissionCodes): void
    {
        foreach ($permissionCodes as $code) {
            if (isset($permissions[$code])) {
                RolePermission::create([
                    'role_id' => $role->id,
                    'permission_id' => $permissions[$code]->id,
                ]);
            }
        }
    }
}