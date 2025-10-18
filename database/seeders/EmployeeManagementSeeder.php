<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeShift;
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

            $employeeUsers = User::whereHas('employeeProfile')
                ->with('employeeProfile')
                ->get();

            $employees = $employeeUsers
                ->filter(fn ($user) => $user->employeeProfile)
                ->mapWithKeys(fn ($user) => [$user->email => $user->employeeProfile]);

            if ($employees->isEmpty()) {
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

            $shifts = [];
            $today = Carbon::today();

            // Create shifts for different dates
            $shiftSchedule = [
                ['name' => 'Morning Shift', 'date' => $today->copy()->subDays(2)],
                ['name' => 'Evening Shift', 'date' => $today->copy()->subDay()],
                ['name' => 'Weekend Premium Shift', 'date' => $today->copy()->next('saturday')],
            ];

            foreach ($shiftSchedule as $schedule) {
                $definition = collect($shiftDefinitions)->firstWhere('name', $schedule['name']);
                
                if (!$definition) {
                    continue;
                }

                $shift = Shift::updateOrCreate(
                    [
                        'name' => $definition['name'],
                        'shift_date' => $schedule['date']->toDateString(),
                    ],
                    [
                        'start_time' => $definition['start_time'],
                        'end_time' => $definition['end_time'],
                    ]
                );

                $shifts[$definition['name'] . '_' . $schedule['date']->toDateString()] = $shift;
            }

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
                
                if (!$employee) {
                    continue;
                }

                $assignedDate = $assignment['date'] instanceof Carbon
                    ? $assignment['date']->toDateString()
                    : Carbon::parse($assignment['date'])->toDateString();

                // Find the shift with matching name and date
                $shiftKey = $assignment['shift'] . '_' . $assignedDate;
                $shift = $shifts[$shiftKey] ?? null;

                if (!$shift) {
                    continue;
                }

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
        });
    }
}
