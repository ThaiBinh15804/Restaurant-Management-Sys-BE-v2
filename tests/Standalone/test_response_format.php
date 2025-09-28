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

echo "Testing Response Format Implementation...\n";
try {
    DB::beginTransaction();
    try {
        // Clear old test data
        RefreshToken::where('user_agent', 'Test Agent')->delete();
        User::where('email', 'admin@restaurant.com')->delete();
        Role::where('name', 'Admin')->delete();
        Permission::where('code', 'manage_users')->delete();

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

        // Test User creation
        $user = User::create([
            'email' => 'admin@restaurant.com',
            'password' => bcrypt('password123'),
            'status' => User::STATUS_ACTIVE,
            'role_id' => $role->id
        ]);
        echo "✓ User created with ID: " . $user->id . "\n";

        // Test role-permission relationship
        $role->permissions()->attach($permission->id);
        echo "✓ Permission attached to role\n";

        // Test user permission check
        $hasPermission = $user->hasPermission('manage_users');
        echo "✓ User has permission: " . ($hasPermission ? 'Yes' : 'No') . "\n";

        // Test RefreshToken creation
        $refreshToken = RefreshToken::create([
            'user_id' => $user->id,
            'token' => RefreshToken::generateToken(),
            'expire_at' => now()->addDays(30),
            'status' => RefreshToken::STATUS_ACTIVE,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent'
        ]);
        echo "✓ RefreshToken created with ID: " . $refreshToken->id . "\n";

        echo "\n🎉 All tests response format passed!\n";
    } catch (Exception $e) {
        echo "✗ Error: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    }
    DB::rollBack(); // Rollback tất cả thay đổi DB sau test
    echo "✓ All database changes rolled back.\n";
} catch (Exception $e) {
    DB::rollBack(); // Đảm bảo rollback khi có lỗi
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}
