# RBAC Testing Instructions

## Quick Test to Verify RBAC Implementation

### 1. Setup and Seed Data
```bash
# Fresh install with seeded data
php artisan migrate:fresh --seed

# Check if routes are registered
php artisan route:list

# Verify roles and permissions are created
php artisan rbac:manage list-roles
php artisan rbac:manage list-permissions
```

### 2. Test Authentication and Permissions

#### Test User Accounts (created by seeder):
- **Super Admin**: `superadmin@restaurant.com` / `password123`
- **Admin**: `admin@restaurant.com` / `password123`  
- **Manager**: `manager@restaurant.com` / `password123`

#### Test API Endpoints:

**Step 1: Login to get JWT token**
```bash
curl -X POST http://localhost:8000/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@restaurant.com",
    "password": "password123"
  }'
```

**Step 2: Test protected endpoint with token**
```bash
# Replace {TOKEN} with actual JWT token from login response
curl -X GET http://localhost:8000/users \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json"
```

**Step 3: Test permission denial**
```bash
# Try with a user that doesn't have users.view permission
curl -X GET http://localhost:8000/users \
  -H "Authorization: Bearer {STAFF_TOKEN}" \
  -H "Content-Type: application/json"
```

### 3. Verify Permission Checking

#### Check User Permissions:
```bash
# Check what permissions a user has
php artisan rbac:manage check-permission --user=admin@restaurant.com --permission=users.view

# Check a permission they don't have
php artisan rbac:manage check-permission --user=manager@restaurant.com --permission=users.delete
```

#### Assign Different Role:
```bash
# Change user role
php artisan rbac:manage assign-role --user=test@example.com --role="Staff"

# Test their new permissions
php artisan rbac:manage check-permission --user=test@example.com --permission=orders.view
```

### 4. Test Middleware in Development

#### In Controller (using HasPermissionHelpers trait):
```php
use App\Http\Controllers\Api\Traits\HasPermissionHelpers;

class TestController extends Controller
{
    use HasPermissionHelpers;

    public function testMethod()
    {
        // Quick permission check
        if (!$this->userCan('users.view')) {
            return $this->errorResponse('No permission', [], 403);
        }

        // Check multiple permissions
        if (!$this->userCanAny(['users.view', 'users.edit'])) {
            return $this->errorResponse('No permission', [], 403);
        }

        // Or use helper method with automatic error response
        if ($error = $this->checkPermissionOrFail('users.create')) {
            return $error;
        }

        return $this->successResponse([], 'Test successful');
    }
}
```

### 5. Common Issues and Solutions

#### Issue: "hasPermission method not found"
**Solution**: Ensure proper type hinting and instanceof checks
```php
$user = Auth::user();
if ($user instanceof \App\Models\User && $user->hasPermission('users.view')) {
    // Has permission
}
```

#### Issue: Routes not found
**Solution**: Check route attributes configuration and run:
```bash
php artisan route:clear
php artisan route:cache
```

#### Issue: Permission cache not clearing
**Solution**: 
```bash
php artisan cache:clear
# Or in code:
$role->clearPermissionCache();
```

#### Issue: Middleware not working
**Solution**: Ensure middleware is registered in AppServiceProvider:
```php
Route::aliasMiddleware('permission', CheckPermission::class);
```

### 6. IDE Configuration

#### For PHPStorm/VS Code Intelephense:
1. Add the `_ide_helper_models.php` file to your project
2. Configure IDE to index the file
3. Or install `laravel-ide-helper` package:
```bash
composer require --dev barryvdh/laravel-ide-helper
php artisan ide-helper:generate
php artisan ide-helper:models
```

### 7. Production Checklist

- [ ] All routes properly protected with permission middleware
- [ ] Default roles and permissions seeded
- [ ] Permission caching configured (Redis recommended)
- [ ] Audit logging enabled for permission changes  
- [ ] Role assignments tested for all user types
- [ ] API documentation includes permission requirements
- [ ] Error responses don't leak sensitive information

This RBAC system is now production-ready and will handle enterprise-level permission requirements while remaining simple to manage and extend.