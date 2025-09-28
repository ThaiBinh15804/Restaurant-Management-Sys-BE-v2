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
        // First create roles and permissions
        $this->call(RolePermissionSeeder::class);
        $this->createDefaultUsers();
        $this->createDefaultTableDiskMenuData();

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

    private function createDefaultTableDiskMenuData(): void
    {
        $now = \Carbon\Carbon::now();

        // Lấy lại User vừa tạo
        $employeeUser = User::where('email', 'admin@restaurant.com')->first();
        $customerUser = User::where('email', 'customer@restaurant.com')->first();
        $customerProfile = $customerUser->customerProfile; // Lấy profile vừa tạo
        $employeeProfile = $employeeUser->employeeProfile; // Lấy profile vừa tạo

        // 1. Dining Tables
        $table1 = DiningTable::create([
            'table_number' => 1,
            'capacity' => 4,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        $table2 = DiningTable::create([
            'table_number' => 2,
            'capacity' => 2,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        // 2. Reservation
        $reservation = Reservation::create([
            'customer_id' => $customerProfile->id, // <-- FK đúng,
            'reserved_at' => $now->copy()->addHour(),
            'number_of_people' => 2,
            'status' => 0, // Pending
            'notes' => 'Near window',
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        // 3. Table Session
        $session = TableSession::create([
            'type' => 0, // Offline
            'status' => 1, // Active
            'customer_id' => $customerProfile->id, // <-- FK đúng,
            'employee_id' => $employeeProfile->id,
            'started_at' => $now,
            'ended_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        // 4. Pivot: session - reservation
        TableSessionReservation::create([
            'table_session_id' => $session->id,
            'reservation_id' => $reservation->id,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        // 5. Pivot: session - dining table
        TableSessionDiningTable::create([
            'table_session_id' => $session->id,
            'dining_table_id' => $table1->id,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        // 6. Orders
        $order = Order::create([
            'table_session_id' => $session->id,
            'status' => 0, // Open
            'total_amount' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        // 7. Dish Categories
        $category = DishCategory::create([
            'name' => 'Appetizers',
            'desc' => 'Starters',
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        // 8. Dishes
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

        // 9. Order Items
        OrderItem::create([
            'order_id' => $order->id,
            'dish_id' => $dish->id,
            'quantity' => 2,
            'price' => 5,
            'total_price' => 10,
            'status' => 0, // Ordered
            'notes' => 'Extra spicy',
            'prepared_by' => $employeeProfile->id,
            'served_at' => null,
            'cancelled_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        // 10. Menus
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

        // 11. Menu Items
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
