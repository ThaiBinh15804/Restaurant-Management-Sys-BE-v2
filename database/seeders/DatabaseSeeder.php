<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // First create roles and permissions
        $this->call(RolePermissionSeeder::class);
        
        // Then create default users
        $this->createDefaultUsers();
    }

    /**
     * Create default users with roles
     */
    private function createDefaultUsers(): void
    {
        $superAdminRole = Role::where('name', 'Super Administrator')->first();
        $adminRole = Role::where('name', 'Administrator')->first();
        $managerRole = Role::where('name', 'Manager')->first();
        $staffRole = Role::where('name', 'Staff')->first();

        $superAdmin = User::create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@restaurant.com',
            'password' => 'password123',
            'status' => User::STATUS_ACTIVE,
            'role_id' => $superAdminRole?->id,
        ]);

        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin@restaurant.com',
            'password' => 'password123',
            'status' => User::STATUS_ACTIVE,
            'role_id' => $adminRole?->id,
        ]);

        $manager = User::create([
            'name' => 'Restaurant Manager',
            'email' => 'manager@restaurant.com',
            'password' => 'password123',
            'status' => User::STATUS_ACTIVE,
            'role_id' => $managerRole?->id,
        ]);

        $staff = User::create([
            'name' => 'Staff Member',
            'email' => 'staff@restaurant.com',
            'password' => 'password123',
            'status' => User::STATUS_ACTIVE,
            'role_id' => $staffRole?->id, 
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role_id' => $managerRole?->id,
        ]);
    }
}
