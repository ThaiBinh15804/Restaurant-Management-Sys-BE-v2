<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Permissions Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the definition of all permissions in the system.
    | Permissions are organized by modules for better management and clarity.
    | Each permission has a unique code, name, description, and status.
    |
    | IMPORTANT: Only permissions are synced from this config to database.
    | Roles and role-permissions are created initially but can be managed
    | by users afterwards without being overwritten by sync operations.
    |
    */

    'modules' => [
        'users' => [
            'name' => 'User Management',
            'description' => 'Permissions related to user management',
            'permissions' => [
                'users.view' => [
                    'name' => 'View Users',
                    'description' => 'Permission to view user listings and details',
                ],
                'users.create' => [
                    'name' => 'Create Users',
                    'description' => 'Permission to create new user accounts',
                ],
                'users.edit' => [
                    'name' => 'Edit Users',
                    'description' => 'Permission to edit existing user accounts',
                ],
                'users.delete' => [
                    'name' => 'Delete Users',
                    'description' => 'Permission to delete user accounts',
                ],
                'users.manage_roles' => [
                    'name' => 'Manage User Roles',
                    'description' => 'Permission to assign and modify user roles',
                ],
            ],
        ],

        'roles' => [
            'name' => 'Role Management',
            'description' => 'Permissions related to role management',
            'permissions' => [
                'roles.view' => [
                    'name' => 'View Roles',
                    'description' => 'Permission to view role listings and details',
                ],
                'roles.create' => [
                    'name' => 'Create Roles',
                    'description' => 'Permission to create new roles',
                ],
                'roles.edit' => [
                    'name' => 'Edit Roles',
                    'description' => 'Permission to edit existing roles',
                ],
                'roles.delete' => [
                    'name' => 'Delete Roles',
                    'description' => 'Permission to delete roles',
                ],
                'roles.manage_permissions' => [
                    'name' => 'Manage Role Permissions',
                    'description' => 'Permission to assign and modify role permissions',
                ],
            ],
        ],

        'permissions' => [
            'name' => 'Permission Management',
            'description' => 'Permissions related to permission management',
            'permissions' => [
                'permissions.view' => [
                    'name' => 'View Permissions',
                    'description' => 'Permission to view permission listings and details',
                ],
                'permissions.create' => [
                    'name' => 'Create Permissions',
                    'description' => 'Permission to create new permissions',
                ],
                'permissions.edit' => [
                    'name' => 'Edit Permissions',
                    'description' => 'Permission to edit existing permissions',
                ],
                'permissions.delete' => [
                    'name' => 'Delete Permissions',
                    'description' => 'Permission to delete permissions',
                ],
            ],
        ],

        'categories' => [
            'name' => 'Category Management',
            'description' => 'Permissions related to menu category management',
            'permissions' => [
                'categories.view' => [
                    'name' => 'View Categories',
                    'description' => 'Permission to view menu categories',
                ],
                'categories.create' => [
                    'name' => 'Create Categories',
                    'description' => 'Permission to create new menu categories',
                ],
                'categories.edit' => [
                    'name' => 'Edit Categories',
                    'description' => 'Permission to edit menu categories',
                ],
                'categories.delete' => [
                    'name' => 'Delete Categories',
                    'description' => 'Permission to delete menu categories',
                ],
            ],
        ],

        'products' => [
            'name' => 'Product Management',
            'description' => 'Permissions related to product/menu item management',
            'permissions' => [
                'products.view' => [
                    'name' => 'View Products',
                    'description' => 'Permission to view products and menu items',
                ],
                'products.create' => [
                    'name' => 'Create Products',
                    'description' => 'Permission to create new products and menu items',
                ],
                'products.edit' => [
                    'name' => 'Edit Products',
                    'description' => 'Permission to edit products and menu items',
                ],
                'products.delete' => [
                    'name' => 'Delete Products',
                    'description' => 'Permission to delete products and menu items',
                ],
                'products.manage_pricing' => [
                    'name' => 'Manage Product Pricing',
                    'description' => 'Permission to manage product prices and pricing strategies',
                ],
            ],
        ],

        'orders' => [
            'name' => 'Order Management',
            'description' => 'Permissions related to order processing and management',
            'permissions' => [
                'orders.view' => [
                    'name' => 'View Orders',
                    'description' => 'Permission to view order listings and details',
                ],
                'orders.create' => [
                    'name' => 'Create Orders',
                    'description' => 'Permission to create new orders',
                ],
                'orders.edit' => [
                    'name' => 'Edit Orders',
                    'description' => 'Permission to edit existing orders',
                ],
                'orders.delete' => [
                    'name' => 'Cancel/Delete Orders',
                    'description' => 'Permission to cancel or delete orders',
                ],
                'orders.process' => [
                    'name' => 'Process Orders',
                    'description' => 'Permission to process and fulfill orders',
                ],
                'orders.complete' => [
                    'name' => 'Complete Orders',
                    'description' => 'Permission to mark orders as completed',
                ],
                'orders.refund' => [
                    'name' => 'Refund Orders',
                    'description' => 'Permission to process order refunds',
                ],
            ],
        ],

        'tables' => [
            'name' => 'Table Management',
            'description' => 'Permissions related to restaurant table management',
            'permissions' => [
                'tables.view' => [
                    'name' => 'View Tables',
                    'description' => 'Permission to view restaurant tables',
                ],
                'tables.create' => [
                    'name' => 'Create Tables',
                    'description' => 'Permission to create new restaurant tables',
                ],
                'tables.edit' => [
                    'name' => 'Edit Tables',
                    'description' => 'Permission to edit restaurant table details',
                ],
                'tables.delete' => [
                    'name' => 'Delete Tables',
                    'description' => 'Permission to delete restaurant tables',
                ],
                'tables.manage_status' => [
                    'name' => 'Manage Table Status',
                    'description' => 'Permission to change table availability status',
                ],
            ],
        ],

        'reservations' => [
            'name' => 'Reservation Management',
            'description' => 'Permissions related to table reservation management',
            'permissions' => [
                'reservations.view' => [
                    'name' => 'View Reservations',
                    'description' => 'Permission to view table reservations',
                ],
                'reservations.create' => [
                    'name' => 'Create Reservations',
                    'description' => 'Permission to create new table reservations',
                ],
                'reservations.edit' => [
                    'name' => 'Edit Reservations',
                    'description' => 'Permission to edit existing reservations',
                ],
                'reservations.delete' => [
                    'name' => 'Cancel Reservations',
                    'description' => 'Permission to cancel table reservations',
                ],
                'reservations.confirm' => [
                    'name' => 'Confirm Reservations',
                    'description' => 'Permission to confirm table reservations',
                ],
            ],
        ],

        'inventory' => [
            'name' => 'Inventory Management',
            'description' => 'Permissions related to inventory and stock management',
            'permissions' => [
                'inventory.view' => [
                    'name' => 'View Inventory',
                    'description' => 'Permission to view inventory items and stock levels',
                ],
                'inventory.create' => [
                    'name' => 'Create Inventory Items',
                    'description' => 'Permission to create new inventory items',
                ],
                'inventory.edit' => [
                    'name' => 'Edit Inventory',
                    'description' => 'Permission to edit inventory item details',
                ],
                'inventory.delete' => [
                    'name' => 'Delete Inventory Items',
                    'description' => 'Permission to delete inventory items',
                ],
                'inventory.adjust' => [
                    'name' => 'Adjust Inventory Levels',
                    'description' => 'Permission to adjust inventory stock levels',
                ],
                'inventory.reports' => [
                    'name' => 'View Inventory Reports',
                    'description' => 'Permission to view inventory and stock reports',
                ],
            ],
        ],

        'reports' => [
            'name' => 'Reporting & Analytics',
            'description' => 'Permissions related to reports and analytics',
            'permissions' => [
                'reports.sales' => [
                    'name' => 'View Sales Reports',
                    'description' => 'Permission to view sales and revenue reports',
                ],
                'reports.inventory' => [
                    'name' => 'View Inventory Reports',
                    'description' => 'Permission to view inventory and stock reports',
                ],
                'reports.financial' => [
                    'name' => 'View Financial Reports',
                    'description' => 'Permission to view financial and accounting reports',
                ],
                'reports.customer' => [
                    'name' => 'View Customer Reports',
                    'description' => 'Permission to view customer analytics and reports',
                ],
                'reports.export' => [
                    'name' => 'Export Reports',
                    'description' => 'Permission to export reports in various formats',
                ],
            ],
        ],

        'system' => [
            'name' => 'System Administration',
            'description' => 'Permissions related to system administration and maintenance',
            'permissions' => [
                'system.settings' => [
                    'name' => 'Manage System Settings',
                    'description' => 'Permission to manage system configuration and settings',
                ],
                'system.logs' => [
                    'name' => 'View System Logs',
                    'description' => 'Permission to view system logs and audit trails',
                ],
                'system.backup' => [
                    'name' => 'Perform System Backup',
                    'description' => 'Permission to perform system backup operations',
                ],
                'system.maintenance' => [
                    'name' => 'Perform System Maintenance',
                    'description' => 'Permission to perform system maintenance tasks',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Role Definitions (Initial Setup Only)
    |--------------------------------------------------------------------------
    |
    | This section defines the default roles and their initial permissions.
    | These are only used for initial setup during seeding/migration.
    | 
    | IMPORTANT: After initial setup, roles and role-permissions should be
    | managed through the admin interface or management commands, not by
    | modifying this config file. This config serves as documentation
    | of the initial system setup.
    |
    | Use 'php artisan permissions:sync --with-roles' only if you want to
    | create missing default roles (existing ones won't be modified).
    |
    */

    'roles' => [
        'super_admin' => [
            'name' => 'Super Administrator',
            'description' => 'Full system access with all permissions',
            'permissions' => '*', // All permissions
        ],

        'admin' => [
            'name' => 'Administrator',
            'description' => 'Administrative access with most permissions',
            'permissions' => [
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
            ],
        ],

        'manager' => [
            'name' => 'Manager',
            'description' => 'Restaurant manager with operational permissions',
            'permissions' => [
                'users.view', 'users.create', 'users.edit',
                'roles.view',
                'categories.view', 'categories.create', 'categories.edit',
                'products.view', 'products.create', 'products.edit', 'products.manage_pricing',
                'orders.view', 'orders.create', 'orders.edit', 'orders.process', 'orders.complete', 'orders.refund',
                'tables.view', 'tables.create', 'tables.edit', 'tables.manage_status',
                'reservations.view', 'reservations.create', 'reservations.edit', 'reservations.delete', 'reservations.confirm',
                'inventory.view', 'inventory.edit', 'inventory.adjust', 'inventory.reports',
                'reports.sales', 'reports.inventory', 'reports.financial', 'reports.customer',
            ],
        ],

        'staff' => [
            'name' => 'Staff',
            'description' => 'Restaurant staff with limited permissions',
            'permissions' => [
                'categories.view',
                'products.view',
                'orders.view', 'orders.create', 'orders.edit',
                'tables.view', 'tables.manage_status',
                'reservations.view', 'reservations.create', 'reservations.edit',
                'inventory.view',
            ],
        ],

        'cashier' => [
            'name' => 'Cashier',
            'description' => 'Point of sale and order processing permissions',
            'permissions' => [
                'categories.view',
                'products.view',
                'orders.view', 'orders.create', 'orders.edit', 'orders.process', 'orders.complete',
                'tables.view', 'tables.manage_status',
                'reservations.view',
                'reports.sales',
            ],
        ],

        'kitchen' => [
            'name' => 'Kitchen Staff',
            'description' => 'Kitchen operations and order management',
            'permissions' => [
                'products.view',
                'orders.view', 'orders.process', 'orders.complete',
                'inventory.view', 'inventory.adjust',
            ],
        ],

        'waiter' => [
            'name' => 'Waiter/Server',
            'description' => 'Order taking and table service permissions',
            'permissions' => [
                'categories.view',
                'products.view',
                'orders.view', 'orders.create', 'orders.edit',
                'tables.view', 'tables.manage_status',
                'reservations.view', 'reservations.create', 'reservations.edit',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for permission synchronization
    |
    | PERMISSIONS:
    | - auto_create_missing: Create new permissions from config
    | - auto_disable_unused: Disable permissions not in config
    | - preserve_custom_permissions: Keep manually created permissions
    |
    | ROLES & ROLE-PERMISSIONS:
    | - Only created if they don't exist (no overwrite)
    | - Managed by users after initial setup
    | - Config serves as initial setup documentation only
    |
    */

    'sync' => [
        // Permission sync settings (fully managed by config)
        'auto_create_missing' => true,
        'auto_disable_unused' => false,
        'preserve_custom_permissions' => true,
        
        // Role sync settings (initial setup only)
        'roles_initial_setup_only' => true,
        'preserve_role_modifications' => true,
    ],
];