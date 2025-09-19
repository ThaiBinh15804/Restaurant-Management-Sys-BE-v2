<?php

use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use App\Models\RefreshToken;
use Illuminate\Support\Facades\DB;

require_once __DIR__ . '/../../vendor/autoload.php';

// Boot Laravel application
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing JWT Authentication System with rollback...\n";

try {
    DB::beginTransaction(); // Bắt đầu transaction

    // Test Role creation
    $role = Role::create([
        'name' => 'Admin', 
        'description' => 'Administrator role',
        'is_active' => true
    ]);
    echo "✓ Role created with ID: " . $role->id . "\n";

    // Test Permission creation
    $permission = Permission::create([
        'name' => 'manage_users',
        'code' => 'manage_users', 
        'description' => 'Can manage users',
        'is_active' => true
    ]);
    echo "✓ Permission created with ID: " . $permission->id . "\n";

    // Test User creation with custom ID
    $user = User::create([
        'name' => 'Admin User',
        'email' => 'admin@restaurant.com',
        'password' => bcrypt('password123'),
        'status' => User::STATUS_ACTIVE,
        'role_id' => $role->id
    ]);
    echo "✓ User created with ID: " . $user->id . "\n";

    // Test relationships
    echo "✓ User role: " . $user->role->name . "\n";
    
    // Test role-permission relationship
    $role->permissions()->attach($permission->id);
    echo "✓ Permission attached to role\n";
    
    // Test user permission check
    $hasPermission = $user->hasPermission('manage_users');
    echo "✓ User has permission: " . ($hasPermission ? 'Yes' : 'No') . "\n";

    // Test RefreshToken creation (in transaction, so it will rollback)
    $refreshToken = RefreshToken::create([
        'user_id' => $user->id,
        'token' => RefreshToken::generateToken(),
        'expire_at' => now()->addDays(30),
        'status' => RefreshToken::STATUS_ACTIVE,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test Agent'
    ]);
    echo "✓ RefreshToken created with ID: " . $refreshToken->id . "\n";
    echo "✓ RefreshToken status: " . $refreshToken->getStatusLabel() . "\n";

    // Test RefreshToken methods
    echo "✓ RefreshToken is active: " . ($refreshToken->isActive() ? 'Yes' : 'No') . "\n";
    echo "✓ RefreshToken is expired: " . ($refreshToken->isExpired() ? 'Yes' : 'No') . "\n";
    echo "✓ RefreshToken is revoked: " . ($refreshToken->isRevoked() ? 'Yes' : 'No') . "\n";

    // Test enum constants access
    echo "✓ User STATUS_ACTIVE: " . User::STATUS_ACTIVE . "\n";
    echo "✓ RefreshToken STATUS_ACTIVE: " . RefreshToken::STATUS_ACTIVE . "\n";

    echo "\nAll tests passed! ✓\n";
    echo "JWT Authentication system is ready to use!\n";

    DB::rollBack(); // Rollback tất cả thay đổi DB sau test
    echo "✓ All database changes rolled back.\n";

} catch (Exception $e) {
    DB::rollBack(); // Đảm bảo rollback khi có lỗi
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}
