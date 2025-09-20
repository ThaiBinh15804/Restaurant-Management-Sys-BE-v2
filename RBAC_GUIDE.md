# RBAC Implementation Guide

## Quick Start

### 1. Seed Default Roles and Permissions
```bash
php artisan migrate:fresh --seed
```

This will create:
- 7 default roles (Super Admin, Admin, Manager, Staff, Cashier, Kitchen, Waiter)
- 40+ permissions covering all system modules
- Default users with appropriate roles

### 2. Using Permission Middleware

#### Protect Routes with Permissions
```php
#[Get('/users', middleware: ['permission:users.view'])]
#[Post('/users', middleware: ['permission:users.create'])]
#[Put('/users/{id}', middleware: ['permission:users.edit'])]
#[Delete('/users/{id}', middleware: ['permission:users.delete'])]
```

#### Multiple Permissions (AND logic)
```php
#[Post('/users/{id}/assign-role', middleware: ['permission:users.edit,users.manage_roles'])]
```

### 3. Check Permissions in Code

#### In Controllers
```php
// Check single permission
if (!auth()->user()->hasPermission('users.create')) {
    return $this->errorResponse('Insufficient permissions', [], 403);
}

// Check multiple permissions (any)
if (!auth()->user()->hasAnyPermission(['users.view', 'users.edit'])) {
    return $this->errorResponse('Insufficient permissions', [], 403);
}

// Check multiple permissions (all)
if (!auth()->user()->hasAllPermissions(['users.edit', 'users.manage_roles'])) {
    return $this->errorResponse('Insufficient permissions', [], 403);
}
```

#### Using Helper Trait
```php
use App\Http\Controllers\Api\Traits\HasPermissionHelpers;

class YourController extends Controller
{
    use HasPermissionHelpers;

    public function someMethod()
    {
        // Quick check with automatic error response
        if ($error = $this->checkPermissionOrFail('users.create')) {
            return $error;
        }

        // Simple boolean checks
        if ($this->userCan('users.edit')) {
            // User has permission
        }
    }
}
```

### 4. Manage Roles and Permissions

#### List Available Roles
```bash
php artisan rbac:manage list-roles
```

#### List Available Permissions
```bash
php artisan rbac:manage list-permissions
```

#### Assign Role to User
```bash
php artisan rbac:manage assign-role --user=user@example.com --role="Manager"
php artisan rbac:manage assign-role --user=U123456 --role="R123456"
```

#### Check User Permissions
```bash
php artisan rbac:manage check-permission --user=user@example.com --permission=users.create
```

### 5. Role Definitions

#### Super Administrator
- **Purpose**: System owner with full access
- **Permissions**: ALL permissions
- **Use Case**: System setup, maintenance, critical operations

#### Administrator  
- **Purpose**: System admin with most permissions
- **Permissions**: All except super admin functions (users.delete, roles.delete, system.backup)
- **Use Case**: Day-to-day administration, user management

#### Manager
- **Purpose**: Restaurant manager with operational control
- **Permissions**: Restaurant operations, reporting, inventory, staff management
- **Use Case**: Restaurant management, scheduling, inventory oversight

#### Staff
- **Purpose**: General restaurant employee
- **Permissions**: Basic operations (view products, create orders, manage tables)
- **Use Case**: General restaurant workers

#### Cashier
- **Purpose**: Point of sale operations
- **Permissions**: Order processing, payment handling, sales reports
- **Use Case**: Cash register, payment processing

#### Kitchen Staff
- **Purpose**: Kitchen operations
- **Permissions**: Order processing, inventory adjustments
- **Use Case**: Food preparation, order completion

#### Waiter/Server
- **Purpose**: Customer service
- **Permissions**: Order taking, table management, reservations
- **Use Case**: Customer service, order management

### 6. Permission Naming Convention

Permissions follow the pattern: `module.action`

#### Common Actions:
- `view` - Read/list resources
- `create` - Create new resources
- `edit` - Update existing resources
- `delete` - Remove resources
- `manage_*` - Special management permissions

#### Examples:
- `users.view` - View user list
- `users.create` - Create new users
- `users.edit` - Edit user details
- `users.delete` - Delete users
- `users.manage_roles` - Assign roles to users
- `orders.process` - Process orders
- `reports.export` - Export reports

### 7. Adding New Permissions

#### Step 1: Create Permission
```php
Permission::create([
    'code' => 'new_module.new_action',
    'name' => 'New Action Description',
    'description' => 'Permission to perform new action in new module',
    'is_active' => true,
]);
```

#### Step 2: Assign to Roles
```php
$role = Role::where('name', 'Manager')->first();
$permission = Permission::where('code', 'new_module.new_action')->first();
$role->addPermissions([$permission->id]);
```

#### Step 3: Protect Routes
```php
#[Get('/new-endpoint', middleware: ['permission:new_module.new_action'])]
```

### 8. Best Practices

1. **Use Descriptive Permission Names**: Make codes self-explanatory
2. **Group Related Permissions**: Use consistent module names
3. **Cache Considerations**: Permission cache is cleared automatically
4. **Test Permissions**: Use the artisan command to verify user permissions
5. **Least Privilege**: Assign minimum necessary permissions
6. **Regular Audits**: Review role assignments periodically

### 9. Troubleshooting

#### Permission Denied Errors
1. Check if user has required permission: `php artisan rbac:manage check-permission --user=email --permission=code`
2. Verify role assignment: `php artisan rbac:manage list-roles`
3. Check permission exists: `php artisan rbac:manage list-permissions`

#### Cache Issues
1. Clear permission cache: `php artisan cache:clear`
2. Or clear specific role cache in code: `$role->clearPermissionCache()`

#### Database Issues
1. Re-run seeders: `php artisan db:seed --class=RolePermissionSeeder`
2. Check foreign key constraints
3. Verify migration order

This RBAC system provides enterprise-level security while remaining simple to use and maintain.