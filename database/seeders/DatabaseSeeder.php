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
            'full_name' => 'Quản lý',
        ]);

        $staff = User::create([
            'email' => 'staff@restaurant.com',
            'password' => 'password123',
            'status' => User::STATUS_ACTIVE,
            'role_id' => $staffRole?->id,
        ]);
        $employeeStaff = $staff->employeeProfile()->create([
            'full_name' => 'Nhân viên phục vụ',
        ]);

        $cashierUser = User::create([
            'email' => 'cashier@restaurant.com',
            'password' => 'password123',
            'status' => User::STATUS_ACTIVE,
            'role_id' => $cashier?->id,
        ]);
        $employeeCashier = $cashierUser->employeeProfile()->create([
            'full_name' => 'Thu ngân ',
        ]);

        $kitchenUser = User::create([
            'email' => 'kichen@restaurant.com',
            'password' => 'password123',
            'status' => User::STATUS_ACTIVE,
            'role_id' => $kitchen?->id,
        ]);
        $employeeKitchen = $kitchenUser->employeeProfile()->create([
            'full_name' => 'Chef Ngọc Tài',
        ]);

        $waiterUser = User::create([
            'email' => 'waiter@restaurant.com',
            'password' => 'password123',
            'status' => User::STATUS_ACTIVE,
            'role_id' => $waiter?->id,
        ]);
        $employeeWaiter = $waiterUser->employeeProfile()->create([
            'full_name' => 'Đức Nghĩa',
        ]);

        $customerUser = User::create([
            'email' => 'customer@restaurant.com',
            'password' => 'password123',
            'status' => User::STATUS_ACTIVE,
            'role_id' => $customer?->id,
        ]);
        $customerProfile = $customerUser->customerProfile()->create([
            'full_name' => 'Công Tiến',
        ]);

        $customerUser2 = User::create([
            'email' => 'customer2@restaurant.com',
            'password' => 'password123',
            'status' => User::STATUS_ACTIVE,
            'role_id' => $customer?->id,
        ]);
        $customerProfile2 = $customerUser2->customerProfile()->create([
            'full_name' => 'Minh Thuận',
        ]);

        $customerUserOffline = User::create([
            'email' => 'customerOffline@restaurant.com',
            'password' => 'password123',
            'status' => User::STATUS_ACTIVE,
            'role_id' => $customer?->id,
        ]);
        $customerProfileOffline = $customerUserOffline->customerProfile()->create([
            'full_name' => 'Khách vãng lai',
        ]);
    }

    private function createDefaultTableDiskMenuData(): void
    {
        $now = \Carbon\Carbon::now();

        // Lấy lại User vừa tạo
        $employeeUser = User::where('email', 'admin@restaurant.com')->first();
        $customerUser1 = User::where('email', 'customer@restaurant.com')->first();
        $customerUser2 = User::where('email', 'customer2@restaurant.com')->first();
        $customerUserOffline = User::where('email', 'customerOffline@restaurant.com')->first();

        $employeeProfile = $employeeUser->employeeProfile;
        $customerProfile1 = $customerUser1->customerProfile;
        $customerProfile2 = $customerUser2->customerProfile;
        $customerProfileOffline = $customerUserOffline->customerProfile;

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

        // 2. Tạo 3 Dish Categories (Thể loại món ăn)
        $categories = [];
        $categories[] = DishCategory::create([
            'name' => 'Món Khai Vị',
            'desc' => 'Các món ăn khai vị nhẹ nhàng, kích thích vị giác',
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        $categories[] = DishCategory::create([
            'name' => 'Món Chính',
            'desc' => 'Các món ăn chính, no bụng và giàu dinh dưỡng',
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        $categories[] = DishCategory::create([
            'name' => 'Tráng Miệng',
            'desc' => 'Các món tráng miệng ngọt ngào, sảng khoái',
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        // 3. Tạo 10 Dishes (Món ăn)
        $dishes = [];
        
        // Món Khai Vị
        $dishes[] = Dish::create([
            'name' => 'Gỏi Cuốn Tôm Thịt',
            'price' => 45000,
            'desc' => 'Gỏi cuốn tươi với tôm, thịt heo, rau sống và bún tươi',
            'category_id' => $categories[0]->id,
            'cooking_time' => 10,
            'image' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        $dishes[] = Dish::create([
            'name' => 'Nem Rán Hà Nội',
            'price' => 50000,
            'desc' => 'Nem rán giòn rụm với nhân thịt heo, mộc nhĩ và rau củ',
            'category_id' => $categories[0]->id,
            'cooking_time' => 15,
            'image' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        $dishes[] = Dish::create([
            'name' => 'Salad Rau Củ Quả',
            'price' => 40000,
            'desc' => 'Salad tươi mát với nhiều loại rau củ quả và sốt đặc biệt',
            'category_id' => $categories[0]->id,
            'cooking_time' => 8,
            'image' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        // Món Chính
        $dishes[] = Dish::create([
            'name' => 'Phở Bò Tái',
            'price' => 65000,
            'desc' => 'Phở bò truyền thống Hà Nội với nước dùng trong, thơm',
            'category_id' => $categories[1]->id,
            'cooking_time' => 20,
            'image' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        $dishes[] = Dish::create([
            'name' => 'Cơm Tấm Sườn Bì Chả',
            'price' => 55000,
            'desc' => 'Cơm tấm Sài Gòn với sườn nướng, bì, chả trứng và nước mắm đặc biệt',
            'category_id' => $categories[1]->id,
            'cooking_time' => 18,
            'image' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        $dishes[] = Dish::create([
            'name' => 'Bún Chả Hà Nội',
            'price' => 60000,
            'desc' => 'Bún chả nướng thơm phức với nước mắm chua ngọt đậm đà',
            'category_id' => $categories[1]->id,
            'cooking_time' => 22,
            'image' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        $dishes[] = Dish::create([
            'name' => 'Lẩu Thái Hải Sản',
            'price' => 350000,
            'desc' => 'Lẩu Thái chua cay với hải sản tươi sống (phục vụ 2-3 người)',
            'category_id' => $categories[1]->id,
            'cooking_time' => 30,
            'image' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        // Tráng Miệng
        $dishes[] = Dish::create([
            'name' => 'Chè Khúc Bạch',
            'price' => 30000,
            'desc' => 'Chè khúc bạch mát lạnh với thạch dừa và trái cây',
            'category_id' => $categories[2]->id,
            'cooking_time' => 5,
            'image' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        $dishes[] = Dish::create([
            'name' => 'Bánh Flan Caramel',
            'price' => 25000,
            'desc' => 'Bánh flan truyền thống với lớp caramel đắng ngọt',
            'category_id' => $categories[2]->id,
            'cooking_time' => 5,
            'image' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        $dishes[] = Dish::create([
            'name' => 'Chè Đậu Đỏ Dừa Dầm',
            'price' => 28000,
            'desc' => 'Chè đậu đỏ béo ngậy với dừa dầm thơm lừng',
            'category_id' => $categories[2]->id,
            'cooking_time' => 5,
            'image' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        // 4. Tạo 3 Menus với ít nhất 6 món mỗi menu
        $menus = [];
        
        $menus[] = Menu::create([
            'name' => 'Menu Ăn Sáng',
            'description' => 'Thực đơn phong phú cho bữa sáng đầy năng lượng',
            'version' => 1,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        $menus[] = Menu::create([
            'name' => 'Menu Ăn Trưa',
            'description' => 'Các món ăn trưa đa dạng và bổ dưỡng',
            'version' => 1,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        $menus[] = Menu::create([
            'name' => 'Menu Ăn Tối',
            'description' => 'Thực đơn ăn tối sang trọng và đặc sắc',
            'version' => 1,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        // Gán món vào Menu Ăn Sáng (6 món)
        foreach ([0, 1, 2, 3, 7, 8] as $index) {
            MenuItem::create([
                'menu_id' => $menus[0]->id,
                'dish_id' => $dishes[$index]->id,
                'price' => $dishes[$index]->price,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => $employeeProfile->id,
                'updated_by' => $employeeProfile->id,
            ]);
        }

        // Gán món vào Menu Ăn Trưa (7 món)
        foreach ([0, 1, 3, 4, 5, 7, 8] as $index) {
            MenuItem::create([
                'menu_id' => $menus[1]->id,
                'dish_id' => $dishes[$index]->id,
                'price' => $dishes[$index]->price,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => $employeeProfile->id,
                'updated_by' => $employeeProfile->id,
            ]);
        }

        // Gán món vào Menu Ăn Tối (8 món - tất cả)
        foreach ($dishes as $dish) {
            MenuItem::create([
                'menu_id' => $menus[2]->id,
                'dish_id' => $dish->id,
                'price' => $dish->price,
                'notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => $employeeProfile->id,
                'updated_by' => $employeeProfile->id,
            ]);
        }

        // 5. Tạo 3 Promotions (Chương trình khuyến mãi)
        $promotions = [];
        
        $promotions[] = \App\Models\Promotion::create([
            'code' => 'KHAIGIANG2024',
            'description' => 'Khuyến mãi khai giảng - Giảm 15% cho toàn bộ hóa đơn',
            'discount_percent' => 15.00,
            'start_date' => $now->copy()->subDay(),
            'end_date' => $now->copy()->addDays(10),
            'usage_limit' => 100,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        $promotions[] = \App\Models\Promotion::create([
            'code' => 'CUOITUAN',
            'description' => 'Ưu đãi cuối tuần - Giảm 10% khi ăn tại nhà hàng',
            'discount_percent' => 10.00,
            'start_date' => $now->copy()->subDay(),
            'end_date' => $now->copy()->addDays(10),
            'usage_limit' => 200,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        $promotions[] = \App\Models\Promotion::create([
            'code' => 'KHACHHANGMOI',
            'description' => 'Chào mừng khách hàng mới - Giảm 20% cho lần đầu tiên',
            'discount_percent' => 20.00,
            'start_date' => $now->copy()->subDay(),
            'end_date' => $now->copy()->addDays(10),
            'usage_limit' => 50,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        // 6. Tạo 5 Reservations (Đặt bàn)
        $reservations = [];
        
        $reservations[] = Reservation::create([
            'customer_id' => $customerProfile1->id,
            'reserved_at' => $now->copy()->addHour(),
            'number_of_people' => 2,
            'status' => 0, // Pending
            'notes' => 'Gần cửa sổ, tầng 2',
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        $reservations[] = Reservation::create([
            'customer_id' => $customerProfile2->id,
            'reserved_at' => $now->copy()->addHours(2),
            'number_of_people' => 4,
            'status' => 0,
            'notes' => 'Góc yên tĩnh, có ghế trẻ em',
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        $reservations[] = Reservation::create([
            'customer_id' => $customerProfile1->id,
            'reserved_at' => $now->copy()->addHours(3),
            'number_of_people' => 6,
            'status' => 0,
            'notes' => 'Bàn tròn, gần sân khấu',
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        $reservations[] = Reservation::create([
            'customer_id' => $customerProfile2->id,
            'reserved_at' => $now->copy()->addDay()->addHours(5),
            'number_of_people' => 3,
            'status' => 0,
            'notes' => 'Khu vực điều hòa, không khói thuốc',
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        $reservations[] = Reservation::create([
            'customer_id' => $customerProfile1->id,
            'reserved_at' => $now->copy()->addDays(2)->addHours(7),
            'number_of_people' => 8,
            'status' => 0,
            'notes' => 'Tiệc sinh nhật, cần trang trí bàn',
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        // 7. Tạo 5 Table Sessions Offline (Walk-in)
        $offlineSessions = [];
        
        for ($i = 0; $i < 5; $i++) {
            $offlineSessions[$i] = TableSession::create([
                'type' => 0, // Offline/Walk-in
                'status' => 1, // Active
                'customer_id' => $customerProfileOffline->id,
                'employee_id' => $employeeProfile->id,
                'started_at' => $now->copy()->subMinutes(rand(30, 120)),
                'ended_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => $employeeProfile->id,
                'updated_by' => $employeeProfile->id,
            ]);

            // Gán bàn cho mỗi session offline
            TableSessionDiningTable::create([
                'table_session_id' => $offlineSessions[$i]->id,
                'dining_table_id' => $tables[$i + 3]->id, // Sử dụng bàn 3-7
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => $employeeProfile->id,
                'updated_by' => $employeeProfile->id,
            ]);

            // Tạo order với một vài món ngẫu nhiên
            $order = Order::create([
                'table_session_id' => $offlineSessions[$i]->id,
                'status' => 1, // Active
                'total_amount' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => $employeeProfile->id,
                'updated_by' => $employeeProfile->id,
            ]);

            // Thêm 2-4 món vào mỗi order
            $numItems = rand(2, 4);
            $totalAmount = 0;
            for ($j = 0; $j < $numItems; $j++) {
                $randomDish = $dishes[array_rand($dishes)];
                $quantity = rand(1, 3);
                $totalPrice = $randomDish->price * $quantity;
                $totalAmount += $totalPrice;

                OrderItem::create([
                    'order_id' => $order->id,
                    'dish_id' => $randomDish->id,
                    'quantity' => $quantity,
                    'price' => $randomDish->price,
                    'total_price' => $totalPrice,
                    'status' => 0, // Pending
                    'notes' => null,
                    'prepared_by' => $employeeProfile->id,
                    'served_at' => null,
                    'cancelled_reason' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'created_by' => $employeeProfile->id,
                    'updated_by' => $employeeProfile->id,
                ]);
            }

            // Cập nhật tổng tiền order
            $order->update(['total_amount' => $totalAmount]);
        }

        // 8. Tạo 2 Table Sessions từ Reservation (có đặt trước)
        $session1 = TableSession::create([
            'type' => 0, // Offline
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
            'type' => 0, // Offline
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

        // Liên kết session với reservation
        TableSessionReservation::create([
            'table_session_id' => $session1->id,
            'reservation_id' => $reservations[0]->id,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);
        
        TableSessionReservation::create([
            'table_session_id' => $session2->id,
            'reservation_id' => $reservations[1]->id,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        // Gán bàn cho session từ reservation
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

        // Tạo order cho session1 với vài món
        $order1 = Order::create([
            'table_session_id' => $session1->id,
            'status' => 1, // Active
            'total_amount' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'dish_id' => $dishes[0]->id,
            'quantity' => 2,
            'price' => $dishes[0]->price,
            'total_price' => $dishes[0]->price * 2,
            'status' => 0,
            'notes' => 'Thêm rau sống',
            'prepared_by' => $employeeProfile->id,
            'served_at' => null,
            'cancelled_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'dish_id' => $dishes[3]->id,
            'quantity' => 1,
            'price' => $dishes[3]->price,
            'total_price' => $dishes[3]->price,
            'status' => 0,
            'notes' => 'Không hành',
            'prepared_by' => $employeeProfile->id,
            'served_at' => null,
            'cancelled_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $employeeProfile->id,
            'updated_by' => $employeeProfile->id,
        ]);

        $order1->update(['total_amount' => $dishes[0]->price * 2 + $dishes[3]->price]);
    }
}
