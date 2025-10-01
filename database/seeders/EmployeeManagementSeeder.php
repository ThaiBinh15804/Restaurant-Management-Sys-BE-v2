<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeeManagementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $managerUser = User::where('email', 'manager@restaurant.com')
                ->with('employeeProfile')
                ->first();

            if (!$managerUser) {
                return;
            }

            $employeeUsers = Employee::all();

            $employees = $employeeUsers
                ->filter(fn ($user) => $user->employeeProfile)
                ->mapWithKeys(fn ($user) => [$user->email => $user->employeeProfile])
                ->toArray();

            if (empty($employees)) {
                return;
            }

            $shiftDefinitions = [
                [
                    'name' => 'Morning Shift',
                    'start_time' => '08:00:00',
                    'end_time' => '16:00:00',
                ],
                [
                    'name' => 'Evening Shift',
                    'start_time' => '16:00:00',
                    'end_time' => '00:00:00',
                ],
                [
                    'name' => 'Weekend Premium Shift',
                    'start_time' => '10:00:00',
                    'end_time' => '18:00:00',
                ],
            ];

            $shifts = Shift::all();

            foreach ($shiftDefinitions as $definition) {
                $shift = Shift::updateOrCreate(
                    ['name' => $definition['name']],
                    [
                        'start_time' => $definition['start_time'],
                        'end_time' => $definition['end_time'],
                    ]
                );

                $shifts[$definition['name']] = $shift;
            }

            $today = Carbon::today();

            $assignmentTemplates = [
                [
                    'email' => 'staff@restaurant.com',
                    'shift' => 'Morning Shift',
                    'date' => $today->copy()->subDays(2),
                    'status' => EmployeeShift::STATUS_PRESENT,
                    'check_in_time' => '08:02:00',
                    'check_out_time' => '16:05:00',
                    'overtime_hours' => 1,
                    'notes' => 'Handled breakfast rush and assisted with prep.',
                ],
                [
                    'email' => 'cashier@restaurant.com',
                    'shift' => 'Evening Shift',
                    'date' => $today->copy()->subDay(),
                    'status' => EmployeeShift::STATUS_PRESENT,
                    'check_in_time' => '16:00:00',
                    'check_out_time' => '23:45:00',
                    'overtime_hours' => 0,
                    'notes' => 'Balanced tills and closed out registers.',
                ],
                [
                    'email' => 'kichen@restaurant.com',
                    'shift' => 'Weekend Premium Shift',
                    'date' => $today->copy()->next('saturday'),
                    'status' => EmployeeShift::STATUS_LATE,
                    'check_in_time' => '10:12:00',
                    'check_out_time' => '18:10:00',
                    'overtime_hours' => 2,
                    'notes' => 'Covered extra prep for catering order.',
                ],
            ];

            foreach ($assignmentTemplates as $assignment) {
                $employee = $employees[$assignment['email']] ?? null;
                $shift = $shifts[$assignment['shift']] ?? null;

                if (!$employee || !$shift) {
                    continue;
                }

                $assignedDate = $assignment['date'] instanceof Carbon
                    ? $assignment['date']->toDateString()
                    : Carbon::parse($assignment['date'])->toDateString();

                $checkIn = $assignment['check_in_time']
                    ? Carbon::parse($assignedDate . ' ' . $assignment['check_in_time'])
                    : null;

                $checkOut = $assignment['check_out_time']
                    ? Carbon::parse($assignedDate . ' ' . $assignment['check_out_time'])
                    : null;

                EmployeeShift::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'shift_id' => $shift->id,
                        'assigned_date' => $assignedDate,
                    ],
                    [
                        'status' => $assignment['status'],
                        'check_in' => $checkIn,
                        'check_out' => $checkOut,
                        'overtime_hours' => $assignment['overtime_hours'],
                        'notes' => $assignment['notes'],
                    ]
                );
            }

            $payrollTemplates = [
                [
                    'email' => 'staff@restaurant.com',
                    'month' => $today->copy()->subMonth()->month,
                    'year' => $today->copy()->subMonth()->year,
                    'base_salary' => 1800,
                    'bonus' => 150,
                    'deductions' => 45,
                    'status' => Payroll::STATUS_PAID,
                    'payment_method' => Payroll::PAYMENT_BANK_TRANSFER,
                    'paid_at' => $today->copy()->startOfMonth()->addDays(2),
                    'notes' => 'Monthly salary released with overtime bonus.',
                    'items' => [
                        [
                            'code' => 'OT_BONUS',
                            'item_type' => PayrollItem::TYPE_EARNING,
                            'description' => 'Weekend overtime coverage',
                            'amount' => 120,
                        ],
                        [
                            'code' => 'MEAL_PLAN',
                            'item_type' => PayrollItem::TYPE_DEDUCTION,
                            'description' => 'Meal plan contribution',
                            'amount' => 30,
                        ],
                    ],
                ],
                [
                    'email' => 'cashier@restaurant.com',
                    'month' => $today->month,
                    'year' => $today->year,
                    'base_salary' => 1500,
                    'bonus' => 80,
                    'deductions' => 20,
                    'status' => Payroll::STATUS_DRAFT,
                    'payment_method' => Payroll::PAYMENT_CASH,
                    'notes' => 'Draft payroll awaiting approval.',
                    'items' => [
                        [
                            'code' => 'PERFORMANCE',
                            'item_type' => PayrollItem::TYPE_EARNING,
                            'description' => 'Performance incentive for upselling combos',
                            'amount' => 60,
                        ],
                        [
                            'code' => 'UNIFORM',
                            'item_type' => PayrollItem::TYPE_DEDUCTION,
                            'description' => 'Uniform maintenance fee',
                            'amount' => 15,
                        ],
                    ],
                ],
                [
                    'email' => 'kichen@restaurant.com',
                    'month' => $today->copy()->subMonth()->month,
                    'year' => $today->copy()->subMonth()->year,
                    'base_salary' => 1650,
                    'bonus' => 100,
                    'deductions' => 35,
                    'status' => Payroll::STATUS_PAID,
                    'payment_method' => Payroll::PAYMENT_E_WALLET,
                    'paid_at' => $today->copy()->subWeeks(2),
                    'payment_ref' => 'EWL-'. $today->copy()->subWeeks(2)->format('Ym'),
                    'notes' => 'Includes catering event premium.',
                    'items' => [
                        [
                            'code' => 'CATERING_PREMIUM',
                            'item_type' => PayrollItem::TYPE_EARNING,
                            'description' => 'Premium for catering event support',
                            'amount' => 180,
                        ],
                        [
                            'code' => 'STAFF_MEAL',
                            'item_type' => PayrollItem::TYPE_DEDUCTION,
                            'description' => 'Staff meal plan',
                            'amount' => 25,
                        ],
                    ],
                ],
            ];

            foreach ($payrollTemplates as $template) {
                $employee = $employees[$template['email']] ?? null;

                if (!$employee) {
                    continue;
                }

                $payroll = Payroll::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'month' => $template['month'],
                        'year' => $template['year'],
                    ],
                    [
                        'base_salary' => $template['base_salary'],
                        'bonus' => $template['bonus'],
                        'deductions' => $template['deductions'],
                        'status' => $template['status'],
                        'payment_method' => $template['payment_method'],
                        'payment_ref' => $template['payment_ref'] ?? null,
                        'paid_at' => ($template['status'] === Payroll::STATUS_PAID && isset($template['paid_at']))
                            ? Carbon::parse($template['paid_at'])
                            : null,
                        'notes' => $template['notes'] ?? null,
                        'paid_by' => $template['status'] === Payroll::STATUS_PAID ? $managerUser->employeeProfile?->id : null,
                    ]
                );

                foreach ($template['items'] as $item) {
                    PayrollItem::updateOrCreate(
                        [
                            'payroll_id' => $payroll->id,
                            'code' => $item['code'],
                        ],
                        [
                            'item_type' => $item['item_type'],
                            'description' => $item['description'],
                            'amount' => $item['amount'],
                        ]
                    );
                }

                $earnings = $payroll->items()
                    ->where('item_type', PayrollItem::TYPE_EARNING)
                    ->sum('amount');

                $extraDeductions = $payroll->items()
                    ->where('item_type', PayrollItem::TYPE_DEDUCTION)
                    ->sum('amount');

                $finalSalary = max(
                    0,
                    (float) $payroll->base_salary +
                    (float) $payroll->bonus +
                    (float) $earnings -
                    ((float) $payroll->deductions + (float) $extraDeductions)
                );

                $payroll->forceFill([
                    'final_salary' => round($finalSalary, 2),
                    'updated_by' => $managerUser->employeeProfile?->id,
                ])->save();
            }
        });
    }
}
