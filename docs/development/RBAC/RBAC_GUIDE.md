# RBAC Implementation Guide

## Quick Start

### 1. Seed Default Roles and Permissions
```bash
php artisan migrate:fresh --seed
```

This will create:
- 7 default roles (Super Admin, Admin, Manager, Staff, Cashier, Kitchen, Waiter)
- 55+ permissions covering all system modules
- Default users with appropriate roles

### 2. Using Centralized Permission Management

#### Overview
The system uses a hybrid approach for permission and role management:

**Permissions (Centrally Managed):**
- **Single Source of Truth**: All permissions defined in `config/permissions.php`
- **Bidirectional Sync**: Config can update database permissions
- **Version Control**: Track permission changes through git
- **Idempotent Operations**: Safe to run multiple times

**Roles & Role Permissions (User Managed):**
- **Initial Setup**: Default roles created from config during seeding
- **User Control**: After setup, roles and permissions managed by users
- **No Overwrite**: Sync operations don't modify existing role assignments
- **Flexible Management**: Users can create custom roles and assign permissions

#### Why This Approach?
- **Permissions**: System-level definitions that should be consistent across environments
- **Roles**: Business-level configurations that may vary and evolve based on user needs
- **Flexibility**: Developers control permissions, users control role assignments

#### Configuration Structure
```php
// config/permissions.php
return [
    'modules' => [
        // Permissions are fully managed by this config
        'users' => [
            'name' => 'User Management',
            'permissions' => [
                'users.view' => [
                    'name' => 'View Users',
                    'description' => 'Permission to view user listings',
                ],
            ],
        ],
    ],
    'roles' => [
        // Roles are only for initial setup documentation
        'manager' => [
            'name' => 'Manager',
            'description' => 'Restaurant manager role',
            'permissions' => ['users.view', 'users.create'],
        ],
    ],
];
```

#### Sync Commands
```bash
# Sync only permissions (recommended for regular use)
php artisan rbac sync

# Preview all changes
php artisan rbac sync --dry-run

# Also create missing default roles (use only for initial setup)
php artisan rbac sync --with-roles --dry-run
php artisan rbac sync --with-roles
```

### 3. Managing Permissions and Roles

#### Add New Permission (Centrally Managed)
1. Edit `config/permissions.php`:
```php
'new_module' => [
    'name' => 'New Module',
    'description' => 'Permissions for new functionality',
    'permissions' => [
        'new_module.action' => [
            'name' => 'New Action',
            'description' => 'Permission to perform new action',
        ],
    ],
],
```

2. Sync to database:
```bash
php artisan rbac sync --dry-run  # Preview
php artisan rbac sync             # Apply
```

3. Assign to roles via admin interface or commands

#### Modify Existing Permissions
1. Update permission details in config
2. Run sync command - existing records will be updated
3. Role assignments remain unchanged

#### Role Management (User Controlled)
After initial setup, manage roles through:
- **Admin Interface**: Role management screens
- **Artisan Commands**: `php artisan rbac` commands  
- **API Endpoints**: Role and permission assignment APIs

**Important**: Don't modify role definitions in config after initial setup - they won't sync to database.

### 4. Permission Sync Command Details

#### Regular Permission Sync (Recommended)
```bash
php artisan rbac sync --dry-run
```
**Output Example:**
```
 Starting permissions synchronization...
ℹ️  Only syncing permissions (use --with-roles to sync roles too)

📋 Syncing permissions...
  ➕ Created: new_module.action
  📝 Updated: users.view
📋 Permissions: 56 total (1 created, 55 updated)

⏭️  Skipping roles and role permissions sync
   Use --with-roles flag to sync roles (only creates missing ones)
```

#### Initial Setup with Roles (Use Sparingly)
```bash
php artisan rbac sync --with-roles --dry-run
```
**Output Example:**
```
� Syncing permissions...
📋 Permissions: 55 total (0 created, 55 updated)

👥 Creating missing roles...
  ✓ Already exists: Super Administrator
  ✓ Already exists: Manager
👥 Roles: 0 would be created, 7 already exist

🔗 Creating missing role permissions...
  ✓ Manager: 34 permissions already assigned
🔗 Role Permissions: 0 would be created, 175 already exist
```

#### Key Differences
- **Default behavior**: Only syncs permissions
- **With --with-roles**: Also creates missing default roles (no overwrite)
- **User modifications**: Always preserved, never overwritten

### 5. Using Permission Middleware
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

### 6. Check Permissions in Code

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

#### Centralized Sync Command
```bash
# Preview all permission changes
php artisan rbac sync --dry-run

# Apply permission changes from config
php artisan rbac sync
```

#### Traditional Management Commands
```bash
# List available roles
php artisan rbac list-roles

# List available permissions  
php artisan rbac list-permissions

# Assign role to user
php artisan rbac assign-role --user=user@example.com --role="Manager"
php artisan rbac assign-role --user=U123456 --role="R123456"
```

#### Check User Permissions
```bash
php artisan rbac check-permission --user=user@example.com --permission=users.create
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

### 7. Adding New Permissions (Centralized Method)

#### Recommended Approach - Config First
**Step 1: Update Config File**
Edit `config/permissions.php`:
```php
'new_module' => [
    'name' => 'New Module',
    'description' => 'New functionality permissions',
    'permissions' => [
        'new_module.action' => [
            'name' => 'New Action',
            'description' => 'Permission to perform new action',
        ],
    ],
],
```

**Step 2: Assign to Roles**
In same config file:
```php
'roles' => [
    'manager' => [
        'name' => 'Manager',
        'permissions' => [
            'users.view', 'users.create',
            'new_module.action', // Add new permission
            // ... other permissions
        ],
    ],
],
```

**Step 3: Sync to Database**
```bash
php artisan rbac sync --dry-run  # Preview changes
php artisan rbac sync             # Apply changes
```

**Step 4: Protect Routes**
```php
#[Get('/new-endpoint', middleware: ['permission:new_module.action'])]
```

#### Legacy Method (Manual Database)
⚠️ **Not Recommended**: Use config method above instead.

```php
// Only use if you must create permissions manually
Permission::create([
    'code' => 'new_module.new_action',
    'name' => 'New Action Description', 
    'description' => 'Permission to perform new action in new module',
    'is_active' => true,
]);
```

### 8. Hybrid Management Workflow

#### Development Workflow
1. **Plan Permissions**: Design new permissions in `config/permissions.php`
2. **Test Locally**: Use `--dry-run` to preview changes
3. **Version Control**: Commit config changes to git
4. **Deploy**: Run `php artisan rbac sync` in production
5. **Role Assignment**: Use admin interface to assign new permissions to roles

#### Team Collaboration
- **Permission Consistency**: All environments have same permissions via config
- **Role Flexibility**: Each environment can have different role setups
- **Code Reviews**: Permission changes reviewed through git diff
- **User Control**: Business users manage roles without developer intervention

#### Production Management
- **Permissions**: Managed by developers via config and sync
- **Roles**: Managed by administrators via web interface
- **Separation of Concerns**: Technical vs business configuration clearly separated
- **No Conflicts**: Config changes don't overwrite user role management

### 9. Role and Permission Management Best Practices

#### For Developers (Permissions)
- **Config First**: Always define permissions in config before using
- **Descriptive Codes**: Use clear, self-documenting permission codes
- **Module Grouping**: Organize permissions by functional modules
- **Regular Sync**: Run sync command after permission changes
- **Test Changes**: Always use `--dry-run` first in production

#### For Administrators (Roles)
- **Use Admin Interface**: Manage roles through web interface after initial setup
- **Document Changes**: Keep track of role modifications outside of config
- **Regular Audits**: Review role assignments periodically
- **Least Privilege**: Assign minimum necessary permissions
- **Custom Roles**: Create environment-specific roles as needed

#### Initial Setup Guidelines
- **Use Config**: Initial roles in config serve as templates/documentation
- **One-Time Sync**: Use `--with-roles` only during initial deployment
- **Transition to UI**: Move to web-based role management after setup
- **Preserve Config**: Keep role config as documentation even if not syncing

### 9. Advanced Configuration

#### Module-Based Organization
```php
'modules' => [
    'user_management' => [
        'name' => 'User Management',
        'description' => 'Complete user lifecycle management',
        'permissions' => [
            'users.view' => ['name' => 'View Users', 'description' => '...'],
            'users.create' => ['name' => 'Create Users', 'description' => '...'],
            'users.edit' => ['name' => 'Edit Users', 'description' => '...'],
            'users.delete' => ['name' => 'Delete Users', 'description' => '...'],
            'users.manage_roles' => ['name' => 'Manage User Roles', 'description' => '...'],
        ],
    ],
    'restaurant_operations' => [
        'name' => 'Restaurant Operations',
        'description' => 'Day-to-day restaurant management',
        'permissions' => [
            'orders.view' => ['name' => 'View Orders', 'description' => '...'],
            'orders.create' => ['name' => 'Create Orders', 'description' => '...'],
            'tables.manage_status' => ['name' => 'Manage Table Status', 'description' => '...'],
        ],
    ],
];
```

#### Flexible Role Definitions
```php
'roles' => [
    'super_admin' => [
        'name' => 'Super Administrator',
        'description' => 'Full system access',
        'permissions' => '*', // All permissions
    ],
    'department_manager' => [
        'name' => 'Department Manager',
        'description' => 'Manages specific department',
        'permissions' => [
            // Include all permissions from user_management module
            ...array_keys(config('permissions.modules.user_management.permissions')),
            // Plus specific restaurant operations
            'orders.view', 'orders.edit', 'tables.manage_status',
        ],
    ],
];
```

#### Sync Configuration Options
```php
'sync' => [
    'auto_create_missing' => true,        // Create missing permissions/roles
    'auto_disable_unused' => false,      // Disable permissions not in config
    'preserve_custom_permissions' => true, // Keep manually created permissions
    'clear_role_permissions' => true,    // Clear and reassign role permissions
];
```

### 10. Best Practices for Hybrid Management

1. **Permission Management (Config-Driven)**:
   - Define all permissions in config
   - Use descriptive codes and names
   - Group by functional modules
   - Sync regularly after changes
   - Version control all permission changes

2. **Role Management (User-Driven)**:
   - Use admin interface for role modifications
   - Create environment-specific roles as needed
   - Document role changes outside of config
   - Regular audit of role assignments
   - Apply least privilege principle

3. **System Integration**:
   - Clear separation between technical and business configuration
   - Regular sync of permissions without affecting roles
   - Use dry-run mode for production deployments
   - Maintain role config as documentation only

### 11. Troubleshooting

#### Centralized Management Issues
1. **Config Syntax Errors**: Check PHP syntax in `config/permissions.php`
2. **Sync Failures**: Review console output for detailed error messages
3. **Permission Conflicts**: Use `--dry-run` to identify issues before applying

#### Permission Denied Errors
1. Check if user has required permission: `php artisan rbac check-permission --user=email --permission=code`
2. Verify role assignment: `php artisan rbac list-roles`
3. Check permission exists: `php artisan rbac list-permissions`
4. Re-sync if needed: `php artisan rbac sync`

#### Cache Issues
1. Clear permission cache: `php artisan cache:clear`
2. Or clear specific role cache in code: `$role->clearPermissionCache()`

#### Database Issues
1. Re-run seeders: `php artisan db:seed --class=RolePermissionSeeder`
2. Sync from config: `php artisan rbac sync`
3. Check foreign key constraints
4. Verify migration order

### 12. Migration from Manual Permission Management

If you have existing permissions created manually:

1. **Export Current State**: Document existing permissions and roles
2. **Update Config**: Add existing permissions to `config/permissions.php`
3. **Test Sync**: Use `--dry-run` to verify config matches database
4. **Apply Sync**: Run `php artisan rbac sync`
5. **Verify**: Check all existing functionality still works

This centralized RBAC system provides enterprise-level security with simplified management and maintenance.