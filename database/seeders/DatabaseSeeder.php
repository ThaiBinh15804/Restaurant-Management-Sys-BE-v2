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
        $this->createDefaultUsers();

        // Then create default users
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
        $cashier = Role::where('name', 'Cashier')->first();
        $kitchen = Role::where('name', 'Kitchen Staff')->first();
        $waiter = Role::where('name', 'Waiter')->first();
        $customer = Role::where('name', 'Customer')->first();

        $superAdmin = User::create([
            'email' => 'superadmin@restaurant.com',
            'password' => 'password123',
            'status' => User::STATUS_ACTIVE,
            'role_id' => $superAdminRole?->id,
        ]);
        $employeeSuperAdmin = $superAdmin->employeeProfile()->create([
            'full_name' => 'Super Admin',
        ]);

        $admin = User::create([
            'email' => 'admin@restaurant.com',
            'password' => 'password123',
            'status' => User::STATUS_ACTIVE,
            'role_id' => $adminRole?->id,
        ]);
        $employeeAdmin = $admin->employeeProfile()->create([
            'full_name' => 'Admin User',
        ]);

        $manager = User::create([
            'email' => 'manager@restaurant.com',
            'password' => 'password123',
            'status' => User::STATUS_ACTIVE,
            'role_id' => $managerRole?->id,
        ]);
        $employeeManager = $manager->employeeProfile()->create([
            'full_name' => 'Manager User',
        ]);

        $staff = User::create([
            'email' => 'staff@restaurant.com',
            'password' => 'password123',
            'status' => User::STATUS_ACTIVE,
            'role_id' => $staffRole?->id,
        ]);
        $employeeStaff = $staff->employeeProfile()->create([
            'full_name' => 'Staff User',
        ]);

        $cashierUser = User::create([
            'email' => 'cashier@restaurant.com',
            'password' => 'password123',
            'status' => User::STATUS_ACTIVE,
            'role_id' => $cashier?->id,
        ]);
        $employeeCashier = $cashierUser->employeeProfile()->create([
            'full_name' => 'Cashier User',
        ]);

        $kitchenUser = User::create([
            'email' => 'kichen@restaurant.com',
            'password' => 'password123',
            'status' => User::STATUS_ACTIVE,
            'role_id' => $kitchen?->id,
        ]);
        $employeeKitchen = $kitchenUser->employeeProfile()->create([
            'full_name' => 'Kitchen Staff User',
        ]);

        $waiterUser = User::create([
            'email' => 'waiter@restaurant.com',
            'password' => 'password123',
            'status' => User::STATUS_ACTIVE,
            'role_id' => $waiter?->id,
        ]);
        $employeeWaiter = $waiterUser->employeeProfile()->create([
            'full_name' => 'Waiter User',
        ]);

        $customerUser = User::create([
            'email' => 'customer@restaurant.com',
            'password' => 'password123',
            'status' => User::STATUS_ACTIVE,
            'role_id' => $customer?->id,
        ]);
        $customerProfile = $customerUser->customerProfile()->create([
            'full_name' => 'Customer User',
        ]);
    }
}
