<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Customer;
use App\Models\Employee;

class EmployeeCustomer extends Seeder
{
    public function run(): void
    {
        // === Customer gắn với User đã có ===
        $customerUser = User::where('email', 'customer1@restaurant.com')->first();

        if ($customerUser) {
            Customer::create([
                'full_name' => 'Nguyen Van A',
                'phone' => '0901111111',
                'gender' => 'Male',
                'address' => 'Ha Noi',
                'membership_level' => 1,
                'user_id' => $customerUser->id,
            ]);
        }

        // === Employees gắn với Users đã có ===
        $employees = [
            [
                'email' => 'employee1@restaurant.com',
                'full_name' => 'Tran Van B',
                'phone' => '0902222222',
                'gender' => 'Male',
                'address' => 'Da Nang',
                'bank_account' => '123456789',
                'contract_type' => 0,
                'position' => 'Manager',
                'base_salary' => 15000000,
            ],
            [
                'email' => 'employee2@restaurant.com',
                'full_name' => 'Le Thi C',
                'phone' => '0903333333',
                'gender' => 'Female',
                'address' => 'Ho Chi Minh',
                'bank_account' => '987654321',
                'contract_type' => 1,
                'position' => 'Cashier',
                'base_salary' => 8000000,
            ],
            [
                'email' => 'employee3@restaurant.com',
                'full_name' => 'Pham Van D',
                'phone' => '0904444444',
                'gender' => 'Male',
                'address' => 'Hai Phong',
                'bank_account' => '111222333',
                'contract_type' => 0,
                'position' => 'Chef',
                'base_salary' => 12000000,
            ],
            [
                'email' => 'employee4@restaurant.com',
                'full_name' => 'Nguyen Thi E',
                'phone' => '0905555555',
                'gender' => 'Female',
                'address' => 'Can Tho',
                'bank_account' => '444555666',
                'contract_type' => 1,
                'position' => 'Waitress',
                'base_salary' => 7000000,
            ],
        ];

        foreach ($employees as $emp) {
            $user = User::where('email', $emp['email'])->first();

            if ($user) {
                Employee::create([
                    'full_name' => $emp['full_name'],
                    'phone' => $emp['phone'],
                    'gender' => $emp['gender'],
                    'address' => $emp['address'],
                    'bank_account' => $emp['bank_account'],
                    'contract_type' => $emp['contract_type'],
                    'position' => $emp['position'],
                    'base_salary' => $emp['base_salary'],
                    'hire_date' => now(),
                    'is_active' => true,
                    'user_id' => $user->id, // gắn user đã tạo trước đó
                ]);
            }
        }
    }
}
