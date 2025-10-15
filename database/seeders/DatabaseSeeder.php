<?php

namespace Database\Seeders;

use App\Models\DiningTable;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Role;
use App\Models\TableSession;
use App\Models\TableSessionDiningTable;
use App\Models\TableSessionReservation;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->createDefaultUsers();
        $this->call(EmployeeManagementSeeder::class);
        $this->createDefaultTableDiskMenuData();
        $this->call(InventoryManagementSeeder::class);
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

        $customerUser2 = User::create([
            'email' => 'customer2@restaurant.com',
            'password' => 'password123',
            'status' => User::STATUS_ACTIVE,
            'role_id' => $customer?->id,
        ]);
        $customerProfile2 = $customerUser2->customerProfile()->create([
            'full_name' => 'Customer User 2',
        ]);

        $customerUserOffline = User::create([
            'email' => 'customerOffline@restaurant.com',
            'password' => 'password123',
            'status' => User::STATUS_ACTIVE,
            'role_id' => $customer?->id,
        ]);
        $customerProfileOffline = $customerUserOffline->customerProfile()->create([
            'full_name' => 'Customer User Offline',
        ]);
    }

    private function createDefaultTableDiskMenuData(): void
    {
        $now = \Carbon\Carbon::now();

        // Lấy lại User vừa tạo
        $employeeUser = User::where('email', 'admin@restaurant.com')->first();
        $customerUser1 = User::where('email', 'customer@restaurant.com')->first();
        $customerUser2 = User::where('email', 'customer2@restaurant.com')->first();

        $employeeProfile = $employeeUser->employeeProfile;
        $customerProfile1 = $customerUser1->customerProfile;
        $customerProfile2 = $customerUser2->customerProfile;

        // 1. Tạo 10 Dining Tables
        $tables = [];
        for ($i = 1; $i <= 10; $i++) {
            $tables[$i] = DiningTable::create([
                'table_number' => $i,
                'capacity' => rand(2, 6),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => $employeeProfile->id,
                'updated_by' => $employeeProfile->id,
            ]);
        }

        // 2. Tạo 2 Reservations
        $reservation1 = Reservation::create([
            'customer_id' => $customerProfile1->id,
            'reserved_at' => $now->copy()->addHour(),
            'number_of_people' => 2,
            'status' => 0,
            'notes' => 'Near window',
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        $reservation2 = Reservation::create([
            'customer_id' => $customerProfile2->id,
            'reserved_at' => $now->copy()->addHours(2),
            'number_of_people' => 4,
            'status' => 0,
            'notes' => 'Near door',
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        // 3. Tạo 2 Table Sessions
        $session1 = TableSession::create([
            'type' => 0,
            'status' => 1, // Active
            'customer_id' => $customerProfile1->id,
            'employee_id' => $employeeProfile->id,
            'started_at' => $now,
            'ended_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        $session2 = TableSession::create([
            'type' => 0,
            'status' => 0, // Pending
            'customer_id' => $customerProfile2->id,
            'employee_id' => $employeeProfile->id,
            'started_at' => $now->copy()->addHours(2),
            'ended_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        // 4. Pivot: session - reservation
        TableSessionReservation::create([
            'table_session_id' => $session1->id,
            'reservation_id' => $reservation1->id,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);
        TableSessionReservation::create([
            'table_session_id' => $session2->id,
            'reservation_id' => $reservation2->id,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        // 5. Pivot: session - dining table (gán table 1 và table 2)
        TableSessionDiningTable::create([
            'table_session_id' => $session1->id,
            'dining_table_id' => $tables[1]->id,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);
        TableSessionDiningTable::create([
            'table_session_id' => $session2->id,
            'dining_table_id' => $tables[2]->id,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        // 6. Tạo 1 Order + Dish + Category + OrderItem + Menu + MenuItem cho session1
        $category = DishCategory::create([
            'name' => 'Appetizers',
            'desc' => 'Starters',
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        $dish = Dish::create([
            'name' => 'Spring Roll',
            'price' => 5,
            'desc' => 'Crispy rolls',
            'category_id' => $category->id,
            'cooking_time' => 10,
            'image' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        $order1 = Order::create([
            'table_session_id' => $session1->id,
            'status' => 0,
            'total_amount' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'dish_id' => $dish->id,
            'quantity' => 2,
            'price' => 5,
            'total_price' => 10,
            'status' => 0,
            'notes' => 'Extra spicy',
            'prepared_by' => $employeeProfile->id,
            'served_at' => null,
            'cancelled_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        $menu = Menu::create([
            'name' => 'Lunch Menu',
            'description' => 'Lunch specials',
            'version' => 1,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        MenuItem::create([
            'menu_id' => $menu->id,
            'dish_id' => $dish->id,
            'price' => 5,
            'notes' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);
    }
}
