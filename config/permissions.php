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

        'auth' => [
            'name' => 'Authentication',
            'description' => 'Permissions related to authentication and registration',
            'permissions' => [
                'auth.register' => [
                    'name' => 'Register Account',
                    'description' => 'Permission to register new user account',
                ],
                'auth.verify_email' => [
                    'name' => 'Verify Email',
                    'description' => 'Permission to verify email address during registration',
                ],
                'auth.resend_verification' => [
                    'name' => 'Resend Verification Email',
                    'description' => 'Permission to resend email verification',
                ],
                'auth.google_login' => [
                    'name' => 'Google Login',
                    'description' => 'Permission to login with Google OAuth',
                ],
                'auth.google_register' => [
                    'name' => 'Google Register',
                    'description' => 'Permission to register new account via Google OAuth',
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

        'employees' => [
            'name' => 'Employee Management',
            'description' => 'Permissions related to employee management',
            'permissions' => [
                'employees.view' => [
                    'name' => 'View Employees',
                    'description' => 'Permission to view employee listings and details',
                ],
                'employees.create' => [
                    'name' => 'Create Employees',
                    'description' => 'Permission to create new employee records',
                ],
                'employees.edit' => [
                    'name' => 'Edit Employees',
                    'description' => 'Permission to edit existing employee records',
                ],
                'employees.delete' => [
                    'name' => 'Delete Employees',
                    'description' => 'Permission to delete employee records',
                ],
                'employees.manage_roles' => [
                    'name' => 'Manage Employee Roles',
                    'description' => 'Permission to assign and modify employee roles',
                ],
            ],
        ],

        'customers' => [
            'name' => 'Customer Management',
            'description' => 'Permissions related to customer management',
            'permissions' => [
                'customers.view' => [
                    'name' => 'View Customers',
                    'description' => 'Permission to view customer listings and details',
                ],
                'customers.create' => [
                    'name' => 'Create Customers',
                    'description' => 'Permission to create new customer records',
                ],
                'customers.edit' => [
                    'name' => 'Edit Customers',
                    'description' => 'Permission to edit existing customer records',
                ],
                'customers.delete' => [
                    'name' => 'Delete Customers',
                    'description' => 'Permission to delete customer records',
                ],
            ],
        ],

        'employee_shifts' => [
            'name' => 'Employee Shift Management',
            'description' => 'Permissions related to employee shift scheduling and management',
            'permissions' => [
                'employee_shifts.view' => [
                    'name' => 'View Employee Shifts',
                    'description' => 'Permission to view employee shift schedules',
                ],
                'employee_shifts.create' => [
                    'name' => 'Create Employee Shifts',
                    'description' => 'Permission to create new employee shift schedules',
                ],
                'employee_shifts.edit' => [
                    'name' => 'Edit Employee Shifts',
                    'description' => 'Permission to edit existing employee shift schedules',
                ],
                'employee_shifts.delete' => [
                    'name' => 'Delete Employee Shifts',
                    'description' => 'Permission to delete employee shift schedules',
                ],
                'employee_shifts.assign' => [
                    'name' => 'Assign Employee Shifts',
                    'description' => 'Permission to assign shifts to employees',
                ],
            ],
        ],

        'payrolls' => [
            'name' => 'Payroll Management',
            'description' => 'Permissions related to payroll and salary management',
            'permissions' => [
                'payrolls.view' => [
                    'name' => 'View Payrolls',
                    'description' => 'Permission to view payroll listings and details',
                ],
                'payrolls.create' => [
                    'name' => 'Create Payrolls',
                    'description' => 'Permission to create new payroll records',
                ],
                'payrolls.edit' => [
                    'name' => 'Edit Payrolls',
                    'description' => 'Permission to edit existing payroll records',
                ],
                'payrolls.delete' => [
                    'name' => 'Delete Payrolls',
                    'description' => 'Permission to delete payroll records',
                ],
                'payrolls.process' => [
                    'name' => 'Process Payrolls',
                    'description' => 'Permission to process employee salaries and payments',
                ],
            ],
        ],

        'payroll-items' => [
            'name' => 'Payroll Item Management',
            'description' => 'Permissions related to individual payroll items management',
            'permissions' => [
                'payroll_items.view' => [
                    'name' => 'View Payroll Items',
                    'description' => 'Permission to view payroll item listings and details',
                ],
                'payroll_items.create' => [
                    'name' => 'Create Payroll Items',
                    'description' => 'Permission to create new payroll items',
                ],
                'payroll_items.edit' => [
                    'name' => 'Edit Payroll Items',
                    'description' => 'Permission to edit existing payroll items',
                ],
                'payroll_items.delete' => [
                    'name' => 'Delete Payroll Items',
                    'description' => 'Permission to delete payroll items',
                ],
            ],
        ],

        'shifts' => [
            'name' => 'Shift Management',
            'description' => 'Permissions related to shift definitions and management',
            'permissions' => [
                'shifts.view' => [
                    'name' => 'View Shifts',
                    'description' => 'Permission to view shift listings and details',
                ],
                'shifts.create' => [
                    'name' => 'Create Shifts',
                    'description' => 'Permission to create new shift definitions',
                ],
                'shifts.edit' => [
                    'name' => 'Edit Shifts',
                    'description' => 'Permission to edit existing shift definitions',
                ],
                'shifts.delete' => [
                    'name' => 'Delete Shifts',
                    'description' => 'Permission to delete shift definitions',
                ],
            ],
        ],

        'dish_categories' => [
            'name' => 'Dish Category Management',
            'description' => 'Permissions related to managing dish categories in the menu',
            'permissions' => [
                'dish_categories.view' => [
                    'name' => 'View Dish Categories',
                    'description' => 'Permission to view list and details of dish categories',
                ],
                'dish_categories.create' => [
                    'name' => 'Create Dish Categories',
                    'description' => 'Permission to create new dish categories',
                ],
                'dish_categories.edit' => [
                    'name' => 'Edit Dish Categories',
                    'description' => 'Permission to edit existing dish categories',
                ],
                'dish_categories.delete' => [
                    'name' => 'Delete Dish Categories',
                    'description' => 'Permission to delete dish categories',
                ],
            ],
        ],

        'dishes' => [
            'name' => 'Dish Management',
            'description' => 'Permissions related to managing dishes (menu items) in the restaurant',
            'permissions' => [
                'dishes.view' => [
                    'name' => 'View Dishes',
                    'description' => 'Permission to view list and details of dishes',
                ],
                'dishes.create' => [
                    'name' => 'Create Dishes',
                    'description' => 'Permission to add new dishes to the menu',
                ],
                'dishes.edit' => [
                    'name' => 'Edit Dishes',
                    'description' => 'Permission to modify dish information, pricing, or category',
                ],
                'dishes.delete' => [
                    'name' => 'Delete Dishes',
                    'description' => 'Permission to remove dishes from the menu',
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

        'orderItems' => [
            'name' => 'Order Item Management',
            'description' => 'Permissions related to managing individual order items',
            'permissions' => [
                'orderItems.view' => [
                    'name' => 'View Order Items',
                    'description' => 'Permission to view order item listings and details',
                ],
                'orderItems.create' => [
                    'name' => 'Create Order Items',
                    'description' => 'Permission to add new items to an order',
                ],
                'orderItems.edit' => [
                    'name' => 'Edit Order Items',
                    'description' => 'Permission to edit existing order items (e.g., quantity, status)',
                ],
                'orderItems.delete' => [
                    'name' => 'Delete Order Items',
                    'description' => 'Permission to remove items from an order',
                ],
                'orderItems.updateStatus' => [
                    'name' => 'Update Order Item Status',
                    'description' => 'Permission to change the status of an order item (e.g., cooking, served, cancelled)',
                ],
            ],
        ],

        'menus' => [
            'name' => 'Menus Management',
            'description' => 'Permissions related to managing multiple menus and menu items',
            'permissions' => [
                'menus.view' => [
                    'name' => 'View Menus',
                    'description' => 'Permission to view menu listings and their items',
                ],
                'menus.create' => [
                    'name' => 'Create Menu',
                    'description' => 'Permission to create new menus and add menu items',
                ],
                'menus.edit' => [
                    'name' => 'Edit Menu',
                    'description' => 'Permission to edit existing menus and their items',
                ],
                'menus.delete' => [
                    'name' => 'Delete Menu',
                    'description' => 'Permission to remove menus and their items',
                ],

            ],
        ],

        'dining-tables' => [
            'name' => 'Dining Table Management',
            'description' => 'Permissions related to restaurant dining table management',
            'permissions' => [
                'dining-tables.view' => [
                    'name' => 'View Dining Tables',
                    'description' => 'Permission to view dining tables',
                ],
                'dining-tables.create' => [
                    'name' => 'Create Dining Tables',
                    'description' => 'Permission to create new dining tables',
                ],
                'dining-tables.edit' => [
                    'name' => 'Edit Dining Tables',
                    'description' => 'Permission to edit dining table details',
                ],
                'dining-tables.delete' => [
                    'name' => 'Delete Dining Tables',
                    'description' => 'Permission to delete dining tables',
                ],
                'dining-tables.manage_status' => [
                    'name' => 'Manage Dining Table Status',
                    'description' => 'Permission to change dining table availability status',
                ],
            ],
        ],

        'table-sessions' => [
            'name' => 'Table Session Management',
            'description' => 'Permissions related to restaurant table session management',
            'permissions' => [
                'table-sessions.view' => [
                    'name' => 'View Table Sessions',
                    'description' => 'Permission to view table sessions',
                ],
                'table-sessions.create' => [
                    'name' => 'Create Table Sessions',
                    'description' => 'Permission to create new table sessions',
                ],
                'table-sessions.edit' => [
                    'name' => 'Update Table Sessions',
                    'description' => 'Permission to update table sessions',
                ],
                'table-sessions.delete' => [
                    'name' => 'Delete Table Sessions',
                    'description' => 'Permission to delete table sessions',
                ],
                'table-sessions.merge' => [
                    'name' => 'Merge Table Sessions',
                    'description' => 'Permission to merge multiple table sessions into one',
                ],
                'table-sessions.split' => [
                    'name' => 'Split Table Sessions',
                    'description' => 'Permission to split a table session into multiple sessions',
                ],
                'table-sessions.unmerge' => [
                    'name' => 'Unmerge Table Sessions',
                    'description' => 'Permission to unmerge previously merged table sessions',
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

        'promotions' => [
            'name' => 'Promotion Management',
            'description' => 'Permissions related to promotion management',
            'permissions' => [
                'promotions.view' => [
                    'name' => 'View Promotions',
                    'description' => 'Permission to view promotion listings and details',
                ],
                'promotions.create' => [
                    'name' => 'Create Promotions',
                    'description' => 'Permission to create new promotions',
                ],
                'promotions.edit' => [
                    'name' => 'Edit Promotions',
                    'description' => 'Permission to edit existing promotions',
                ],
                'promotions.delete' => [
                    'name' => 'Delete Promotions',
                    'description' => 'Permission to delete promotions',
                ],
            ],
        ],

        'invoices' => [
            'name' => 'Invoice Management',
            'description' => 'Permissions related to managing invoices and billing',
            'permissions' => [
                'invoices.view' => [
                    'name' => 'View Invoices',
                    'description' => 'Permission to view invoice listings and details',
                ],
                'invoices.create' => [
                    'name' => 'Create Invoices',
                    'description' => 'Permission to create new invoices',
                ],
                'invoices.edit' => [
                    'name' => 'Edit Invoices',
                    'description' => 'Permission to edit existing invoices',
                ],
                'invoices.delete' => [
                    'name' => 'Delete Invoices',
                    'description' => 'Permission to delete invoices',
                ],
            ],
        ],

        'ingredient_categories' => [
            'name' => 'Ingredient Category Management',
            'description' => 'Permissions related to ingredient category management',
            'permissions' => [
                'ingredient_categories.view' => [
                    'name' => 'View Ingredient Categories',
                    'description' => 'Permission to view ingredient category listings and details',
                ],
                'ingredient_categories.create' => [
                    'name' => 'Create Ingredient Categories',
                    'description' => 'Permission to create new ingredient categories',
                ],
                'ingredient_categories.edit' => [
                    'name' => 'Edit Ingredient Categories',
                    'description' => 'Permission to edit existing ingredient categories',
                ],
                'ingredient_categories.delete' => [
                    'name' => 'Delete Ingredient Categories',
                    'description' => 'Permission to delete ingredient categories',
                ],
            ],
        ],

        'ingredients' => [
            'name' => 'Ingredient Management',
            'description' => 'Permissions related to ingredient master data management',
            'permissions' => [
                'ingredients.view' => [
                    'name' => 'View Ingredients',
                    'description' => 'Permission to view ingredient listings and details',
                ],
                'ingredients.create' => [
                    'name' => 'Create Ingredients',
                    'description' => 'Permission to create new ingredients',
                ],
                'ingredients.edit' => [
                    'name' => 'Edit Ingredients',
                    'description' => 'Permission to edit existing ingredients',
                ],
                'ingredients.delete' => [
                    'name' => 'Delete Ingredients',
                    'description' => 'Permission to delete ingredients',
                ],
            ],
        ],

        'suppliers' => [
            'name' => 'Supplier Management',
            'description' => 'Permissions related to supplier management',
            'permissions' => [
                'suppliers.view' => [
                    'name' => 'View Suppliers',
                    'description' => 'Permission to view supplier listings and details',
                ],
                'suppliers.create' => [
                    'name' => 'Create Suppliers',
                    'description' => 'Permission to create new suppliers',
                ],
                'suppliers.edit' => [
                    'name' => 'Edit Suppliers',
                    'description' => 'Permission to edit existing suppliers',
                ],
                'suppliers.delete' => [
                    'name' => 'Delete Suppliers',
                    'description' => 'Permission to delete suppliers',
                ],
            ],
        ],

        'stocks' => [
            'name' => 'Stock Management',
            'description' => 'Permissions related to stock import, export, and loss management',
            'permissions' => [
                'stocks.view' => [
                    'name' => 'View Stocks',
                    'description' => 'Permission to view stock imports, exports, and losses',
                ],
                'stocks.create' => [
                    'name' => 'Create Stock Records',
                    'description' => 'Permission to create stock imports, exports, and losses',
                ],
                'stocks.edit' => [
                    'name' => 'Edit Stock Records',
                    'description' => 'Permission to edit stock imports, exports, and losses',
                ],
                'stocks.delete' => [
                    'name' => 'Delete Stock Records',
                    'description' => 'Permission to delete stock imports, exports, and losses',
                ],
            ],
        ],

        'statistics' => [
            'name' => 'Statistics & Reports',
            'description' => 'Permissions related to viewing system reports and analytics',
            'permissions' => [
                'statistics.view' => [
                    'name' => 'Get reports and statistics',
                    'description' => 'Permission to access various reports and statistics in the system',
                ]
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
                '*',
            ],
            'exclude_permissions' => [
                'permissions.view',
                'permissions.create',
                'permissions.edit',
                'permissions.delete',
                'roles.view',
                'roles.create',
                'roles.edit',
                'roles.delete',
                'roles.manage_permissions',
                'users.manage_roles',
            ],
        ],

        'manager' => [
            'name' => 'Manager',
            'description' => 'Restaurant manager with operational permissions',
            'permissions' => [
                'statistics.view',
                // Bàn & Đặt bàn
                'dining-tables.view',
                'dining-tables.create',
                'dining-tables.edit',
                'dining-tables.delete',
                'dining-tables.manage_status',

                'reservations.view',
                'reservations.create',
                'reservations.edit',
                'reservations.delete',

                'table-sessions.view',
                'table-sessions.create',
                'table-sessions.edit',
                'table-sessions.delete',
                'table-sessions.merge',
                'table-sessions.split',
                'table-sessions.unmerge',

                // Khách hàng
                'customers.view',
                'customers.create',
                'customers.edit',
                'customers.delete',

                // Nhân sự
                'employees.view',
                'employees.create',
                'employees.edit',
                'employees.delete',
                'employees.manage_roles',
                'shifts.view',
                'shifts.create',
                'shifts.edit',
                'shifts.delete',
                'payrolls.view',
                'payrolls.create',
                'payrolls.edit',
                'payrolls.delete',
                'payrolls.process',

                // Menu
                'dish_categories.view',
                'dish_categories.create',
                'dish_categories.edit',
                'dish_categories.delete',
                'dishes.view',
                'dishes.create',
                'dishes.edit',
                'dishes.delete',
                'menus.view',
                'menus.create',
                'menus.edit',
                'menus.delete',

                // Nguyên liệu & Kho
                'ingredients.view',
                'ingredients.create',
                'ingredients.edit',
                'ingredients.delete',
                'suppliers.view',
                'suppliers.create',
                'suppliers.edit',
                'suppliers.delete',
                'stocks.view',
                'stocks.create',
                'stocks.edit',
                'stocks.delete',

                // Tài chính
                'invoices.view',
                'invoices.create',
                'invoices.edit',
                'invoices.delete',
                'promotions.view',
                'promotions.create',
                'promotions.edit',
                'promotions.delete',
            ],
        ],

        'staff' => [
            'name' => 'Staff',
            'description' => 'Restaurant staff with limited view-only permissions',
            'permissions' => [
                'statistics.view',
                'dining-tables.view',
                'reservations.view',
                'customers.view',
                'employees.view',
                'shifts.view',
                'dish_categories.view',
                'dishes.view',
                'menus.view',
                'ingredients.view',
                'suppliers.view',
                'stocks.view',
                'invoices.view',
                'promotions.view',
            ],
        ],

        'cashier' => [
            'name' => 'Cashier',
            'description' => 'Handles billing, promotions, and customer management at the front desk',
            'permissions' => [
                'statistics.view',
                'customers.view',
                'customers.create',
                'customers.edit',
                'customers.delete',
                'reservations.view',
                'invoices.view',
                'invoices.create',
                'invoices.edit',
                'invoices.delete',
                'promotions.view',
                'promotions.create',
                'promotions.edit',
                'promotions.delete',
            ],
        ],

        'kitchen' => [
            'name' => 'Kitchen Staff',
            'description' => 'Manages menu, ingredients, and warehouse operations',
            'permissions' => [
                'statistics.view',
                'dish_categories.view',
                'dish_categories.create',
                'dish_categories.edit',
                'dish_categories.delete',
                'dishes.view',
                'dishes.create',
                'dishes.edit',
                'dishes.delete',
                'menus.view',
                'menus.create',
                'menus.edit',
                'menus.delete',
                'ingredients.view',
                'ingredients.create',
                'ingredients.edit',
                'ingredients.delete',
                'suppliers.view',
                'suppliers.create',
                'suppliers.edit',
                'suppliers.delete',
                'stocks.view',
                'stocks.create',
                'stocks.edit',
                'stocks.delete',
            ],
        ],

        'waiter' => [
            'name' => 'Waiter',
            'description' => 'Handles table service and reservations, with access to menu information',
            'permissions' => [
                'statistics.view',
                'dining-tables.view',
                'dining-tables.manage_status',
                'reservations.view',
                'reservations.create',
                'reservations.edit',
                'reservations.delete',
                'customers.view',
                'menus.view',
                'dishes.view',
            ],
        ],

        'customer' => [
            'name' => 'Customer',
            'description' => 'Limited access for customers',
            'permissions' => [
                'auth.register',
                'auth.verify_email',
                'auth.resend_verification',
                'auth.google_login',

                'table-sessions.view',
                'dishes.view',

                'orders.view',

                'reservations.view',
                'reservations.create',
                'users.view',

                'employees.view',
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
        'auto_disable_unused' => true,
        'preserve_custom_permissions' => true,

        // Role sync settings (initial setup only)
        'roles_initial_setup_only' => false,
        'preserve_role_modifications' => true,
    ],
];
