<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\PayrollGenerateRequest;
use App\Http\Requests\Payroll\PayrollPayRequest;
use App\Http\Requests\Payroll\PayrollQueryRequest;
use App\Http\Requests\Payroll\PayrollStatusRequest;
use App\Http\Requests\Payroll\PayrollUpdateRequest;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Payroll;
use App\Models\PayrollItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use OpenApi\Attributes as OA;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Patch;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Put;

/**
 * @OA\Tag(
 *     name="Payrolls",
 *     description="API Endpoints for Payroll Management"
 * )
 */
#[Prefix('payrolls')]
class PayrollController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/payrolls",
     *     tags={"Payrolls"},
     *     summary="List payrolls",
     *     description="Retrieve a paginated list of payrolls with filters for employee, status, month, and year",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer", default=15, maximum=100)),
     *     @OA\Parameter(name="employee_id", in="query", description="Filter by employee ID", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", description="Filter by payroll status", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="month", in="query", description="Filter by payroll month", @OA\Schema(type="integer", minimum=1, maximum=12)),
     *     @OA\Parameter(name="year", in="query", description="Filter by payroll year", @OA\Schema(type="integer", example=2025)),
     *     @OA\Response(response=200, description="Payrolls retrieved successfully")
     * )
     */
    #[Get('/', middleware: 'permission:payrolls.view')]
    public function index(PayrollQueryRequest $request): JsonResponse
    {
        $filters = $request->filters();
        
        // Mặc định về năm hiện tại nếu không truyền year
        $year = $filters['year'] ?? now()->year;

        $query = Payroll::with(['employee', 'paidByEmployee'])
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->where('year', $year)
            ->when(
                $filters['month'] ?? null,
                fn($q, $v) =>
                $q->where('month', $v)
            )
            ->when(
                array_key_exists('status', $filters) && $filters['status'] !== null,
                fn($q) =>
                $q->where('status', $filters['status'])
            )
            ->when(
                $filters['employee_id'] ?? null,
                fn($q, $v) =>
                $q->where('employee_id', $v)
            );

        $perpage = $request->perPage();
        $paginator = $query->paginate($perpage);

        return $this->successResponse($paginator, 'Payrolls retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/payrolls/{id}",
     *     tags={"Payrolls"},
     *     summary="Show payroll",
     *     description="Retrieve detailed information about a specific payroll, including items and approver",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Payroll ID", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Payroll retrieved successfully"),
     *     @OA\Response(response=404, description="Payroll not found")
     * )
     */
    #[Get('/{id}', middleware: 'permission:payrolls.view')]
    public function show(string $id): JsonResponse
    {
        $payroll = Payroll::with(['employee', 'items', 'paidByEmployee'])->find($id);

        if (!$payroll) {
            return $this->errorResponse('Payroll not found', [], 404);
        }

        return $this->successResponse($payroll, 'Payroll retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/payrolls/generate",
     *     tags={"Payrolls"},
     *     summary="Generate payrolls",
     *     description="Generate payrolls for active employees for a specific month and year",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"month","year"},
     *             @OA\Property(property="month", type="integer", minimum=1, maximum=12, example=9),
     *             @OA\Property(property="year", type="integer", example=2025),
     *             @OA\Property(property="employee_ids", type="array", @OA\Items(type="string"), example={"EMP001","EMP005"}),
     *             @OA\Property(property="overwrite", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Payroll generation completed"),
     *     @OA\Response(response=404, description="No employees found")
     * )
     */
    #[Post('/generate', middleware: 'permission:payrolls.create')]
    public function generate(PayrollGenerateRequest $request): JsonResponse
    {
        $data = $request->validated();
        $month = (int) $data['month'];
        $year = (int) $data['year'];
        $employeeIds = $data['employee_ids'] ?? [];
        $overwrite = (bool) ($data['overwrite'] ?? false);

        $employeesQuery = Employee::query()->where('is_active', true);

        if (!empty($employeeIds)) {
            $employeesQuery->whereIn('id', $employeeIds);
        }

        $employees = $employeesQuery->get();

        if ($employees->isEmpty()) {
            return $this->errorResponse('No employees found for payroll generation.', [], 404);
        }

        $created = [];
        $skipped = [];
        $details = [];

        DB::transaction(function () use ($employees, $month, $year, $overwrite, &$created, &$skipped, &$details) {
            $employeeIds = $employees->pluck('id');

            $assignmentsByEmployee = EmployeeShift::with('shift')
                ->whereIn('employee_id', $employeeIds)
                ->where(function ($query) use ($month, $year) {
                    $query->whereHas('shift', function ($shiftQuery) use ($month, $year) {
                        $shiftQuery->whereYear('shift_date', $year)
                            ->whereMonth('shift_date', $month);
                    })->orWhere(function ($inner) use ($month, $year) {
                        $inner->whereYear('check_in', '=', $year)
                            ->whereMonth('check_in', '=', $month);
                    })->orWhere(function ($inner) use ($month, $year) {
                        $inner->whereYear('check_out', '=', $year)
                            ->whereMonth('check_out', '=', $month);
                    });
                })
                ->get()
                ->groupBy('employee_id');

            foreach ($employees as $employee) {
                $existing = Payroll::where('employee_id', $employee->id)
                    ->where('month', $month)
                    ->where('year', $year)
                    ->first();

                if ($existing && !$overwrite) {
                    $skipped[] = $employee->id;
                    continue;
                }
                
                $assignments = $assignmentsByEmployee->get($employee->id, collect());
                $summary = $this->summarizeEmployeeAssignments($assignments);
                $hourlyRate = $this->calculateHourlyRate((float) ($employee->base_salary ?? 0));
                $workedHours = $summary['worked_minutes'] ? $summary['worked_minutes'] / 60 : 0;
                $diligenceAmount = round($hourlyRate * $workedHours, 2);
                $overtimeAmount = $this->calculateOvertimePay((float) ($employee->base_salary ?? 0), (int) $summary['overtime_hours']);

                Log::info('Employee payroll calculation', [
                    'employee_id' => $employee->id,
                    'month' => $month,
                    'year' => $year,
                    'base_salary' => (float) ($employee->base_salary ?? 0),
                    'worked_hours' => round($workedHours, 2),
                    'overtime_hours' => $summary['overtime_hours'],
                    'hourly_rate' => round($hourlyRate, 2),
                    'diligence_amount' => $diligenceAmount,
                    'overtime_amount' => $overtimeAmount,
                ]);
                
                $payload = [
                    'month' => $month,
                    'year' => $year,
                    'base_salary' => (float) ($employee->base_salary ?? 0),
                    'bonus' => 0.0,
                    'deductions' => 0.0,
                    'final_salary' => 0.0,
                    'status' => Payroll::STATUS_DRAFT,
                    'payment_method' => Payroll::PAYMENT_CASH,
                    'payment_ref' => null,
                    'paid_at' => null,
                    'notes' => null,
                ];

                if ($existing) {
                    $existing->update($payload);
                    $payroll = $existing->fresh();
                } else {
                    $payload['employee_id'] = $employee->id;
                    $payroll = Payroll::create($payload);
                }

                // Tạo/cập nhật PayrollItem: DILIGENCE (lương theo giờ làm thực tế)
                PayrollItem::updateOrCreate(
                    [
                        'payroll_id' => $payroll->id,
                        'code' => 'DILIGENCE',
                    ],
                    [
                        'item_type' => PayrollItem::TYPE_EARNING,
                        'description' => 'Lương thực tế theo giờ làm (' . round($workedHours, 2) . ' giờ × ' . round($hourlyRate, 2) . ')',
                        'amount' => $diligenceAmount,
                    ]
                );

                // Tạo/cập nhật PayrollItem: OVERTIME (nếu có)
                if ($overtimeAmount > 0) {
                    PayrollItem::updateOrCreate(
                        [
                            'payroll_id' => $payroll->id,
                            'code' => 'OVERTIME',
                        ],
                        [
                            'item_type' => PayrollItem::TYPE_EARNING,
                            'description' => 'Phụ cấp tăng ca (' . $summary['overtime_hours'] . ' giờ × ' . round($hourlyRate * 2, 2) . ')',
                            'amount' => $overtimeAmount,
                        ]
                    );
                } else {
                    // Xóa OVERTIME item nếu không có tăng ca
                    PayrollItem::where('payroll_id', $payroll->id)
                        ->where('code', 'OVERTIME')
                        ->delete();
                }

                $finalSalary = $this->updatePayrollTotals($payroll);

                $created[] = $payroll->id;
                $details[] = [
                    'payroll_id' => $payroll->id,
                    'employee_id' => $employee->id,
                    'base_salary' => (float) ($employee->base_salary ?? 0),
                    'hours_worked' => round($workedHours, 2),
                    'overtime_hours' => $summary['overtime_hours'],
                    'hourly_rate' => round($hourlyRate, 2),
                    'diligence_amount' => $diligenceAmount,
                    'overtime_amount' => $overtimeAmount,
                    'final_salary' => $finalSalary,
                ];
            }

            Log::info('Payroll generation completed', [
                'month' => $month,
                'year' => $year,
                'generated_count' => count($created),
                'skipped_count' => count($skipped),
            ]);
        });

        return $this->successResponse([
            'generated' => $created,
            'skipped' => $skipped,
            'details' => $details,
        ], 'Payroll generation completed');
    }

    /**
     * @OA\Put(
     *     path="/api/payrolls/{id}",
     *     tags={"Payrolls"},
     *     summary="Update payroll",
     *     description="Update base salary, bonus, deductions, or metadata for a payroll",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Payroll ID", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="base_salary", type="number", format="float", example=2500),
     *             @OA\Property(property="bonus", type="number", format="float", example=150),
     *             @OA\Property(property="deductions", type="number", format="float", example=30),
     *             @OA\Property(property="notes", type="string", example="Adjusted for performance bonus"),
     *             @OA\Property(property="payment_method", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Payroll updated successfully"),
     *     @OA\Response(response=404, description="Payroll not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    #[Put('/{id}', middleware: 'permission:payrolls.edit')]
    public function update(PayrollUpdateRequest $request, string $id): JsonResponse
    {
        $payroll = Payroll::find($id);

        if (!$payroll) {
            return $this->errorResponse('Payroll not found', [], 404);
        }

        $data = $request->validated();

        $payroll->fill($data);
        $payroll->final_salary = $this->calculateFinalSalary(
            (float) $payroll->base_salary,
            (float) $payroll->bonus,
            (float) $payroll->deductions
        );
        $payroll->save();

        return $this->successResponse($payroll->fresh(['employee', 'items']), 'Payroll updated successfully');
    }

    /**
     * @OA\Patch(
     *     path="/api/payrolls/{id}/status",
     *     tags={"Payrolls"},
     *     summary="Update payroll status",
     *     description="Change the status of a payroll record and optionally update notes",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Payroll ID", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="notes", type="string", example="Pending accounting review")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Payroll status updated successfully"),
     *     @OA\Response(response=404, description="Payroll not found")
     * )
     */
    #[Patch('/{id}/status', middleware: 'permission:payrolls.edit')]
    public function updateStatus(PayrollStatusRequest $request, string $id): JsonResponse
    {
        $payroll = Payroll::find($id);

        if (!$payroll) {
            return $this->errorResponse('Payroll not found', [], 404);
        }

        $payroll->status = $request->integer('status');

        if ($request->filled('notes')) {
            $payroll->notes = $request->input('notes');
        }

        if ($payroll->status !== Payroll::STATUS_PAID) {
            $payroll->paid_at = null;
            $payroll->payment_method = null;
            $payroll->payment_ref = null;
            $payroll->paid_by = null;
        }

        $payroll->save();

        return $this->successResponse($payroll->fresh(['employee']), 'Payroll status updated successfully');
    }

    /**
     * @OA\Patch(
     *     path="/api/payrolls/{id}/pay",
     *     tags={"Payrolls"},
     *     summary="Mark payroll as paid",
     *     description="Record payment details for a payroll and mark it as paid",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Payroll ID", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"payment_method"},
     *             @OA\Property(property="payment_method", type="integer", example=1),
     *             @OA\Property(property="payment_ref", type="string", example="BANK-20240930"),
     *             @OA\Property(property="paid_at", type="string", format="date-time", example="2025-09-30 10:15:00"),
     *             @OA\Property(property="paid_by", type="string", example="EMP001"),
     *             @OA\Property(property="notes", type="string", example="Transferred via bank" )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Payroll paid successfully"),
     *     @OA\Response(response=404, description="Payroll not found"),
     *     @OA\Response(response=409, description="Payroll already marked as paid"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    #[Patch('/{id}/pay', middleware: 'permission:payrolls.edit')]
    public function pay(PayrollPayRequest $request, string $id): JsonResponse
    {
        $payroll = Payroll::find($id);

        if (!$payroll) {
            return $this->errorResponse('Payroll not found', [], 404);
        }

        if ($payroll->status === Payroll::STATUS_PAID) {
            return $this->errorResponse('Payroll already marked as paid.', [], 409);
        }

        $data = $request->validated();

        $payroll->payment_method = $data['payment_method'];
        $payroll->payment_ref = $data['payment_ref'] ?? null;
        $payroll->paid_at = isset($data['paid_at'])
            ? Carbon::createFromFormat('Y-m-d H:i:s', $data['paid_at'])
            : Carbon::now();

        if (!empty($data['paid_by'])) {
            $payroll->paid_by = $data['paid_by'];
        } else {
            $authUser = Auth::user();
            if ($authUser && $authUser->employeeProfile) {
                $payroll->paid_by = $authUser->employeeProfile->id;
            }
        }

        if ($request->filled('notes')) {
            $payroll->notes = $request->input('notes');
        }

        $payroll->status = Payroll::STATUS_PAID;
        $payroll->save();

        Log::info('Payroll marked as paid', [
            'payroll_id' => $payroll->id,
            'paid_by' => $payroll->paid_by,
        ]);

        return $this->successResponse($payroll->fresh(['employee', 'paidByEmployee']), 'Payroll paid successfully');
    }

    private function calculateHourlyRate(float $baseSalary): float
    {
        if ($baseSalary <= 0) {
            return 0.0;
        }

        $standardMonthlyHours = 160; // Default standard working hours per month

        return $baseSalary / $standardMonthlyHours;
    }

    private function summarizeEmployeeAssignments(Collection $assignments): array
    {
        $workedMinutes = 0;
        $overtimeHours = 0;

        foreach ($assignments as $assignment) {
            $shift = $assignment->shift;

            $shiftDate = null;
            if ($shift && $shift->shift_date) {
                $shiftDate = Carbon::parse((string) $shift->shift_date)->toDateString();
            } elseif ($assignment->check_in) {
                $shiftDate = $assignment->check_in->toDateString();
            } elseif ($assignment->check_out) {
                $shiftDate = $assignment->check_out->toDateString();
            }

            $scheduledStart = null;
            $scheduledEnd = null;

            $startTime = $shift ? $shift->getRawOriginal('start_time') : null;
            $endTime = $shift ? $shift->getRawOriginal('end_time') : null;

            if ($shiftDate && $startTime) {
                $scheduledStart = Carbon::createFromFormat('Y-m-d H:i:s', $shiftDate . ' ' . $startTime);
            }

            if ($shiftDate && $endTime) {
                $scheduledEnd = Carbon::createFromFormat('Y-m-d H:i:s', $shiftDate . ' ' . $endTime);
                if ($scheduledStart && $scheduledEnd->lessThanOrEqualTo($scheduledStart)) {
                    $scheduledEnd = $scheduledEnd->copy()->addDay();
                }
            }

            $actualStart = $assignment->check_in ? $assignment->check_in->copy() : ($scheduledStart ? $scheduledStart->copy() : null);
            $actualEnd = $assignment->check_out ? $assignment->check_out->copy() : ($scheduledEnd ? $scheduledEnd->copy() : null);

            if ($actualStart && $actualEnd) {
                if ($actualEnd->lessThanOrEqualTo($actualStart)) {
                    $actualEnd = $actualEnd->copy()->addDay();
                }

                $workedMinutes += $actualStart->diffInMinutes($actualEnd);
            }

            $overtimeHours += max(0, (int) $assignment->overtime_hours);
        }

        return [
            'worked_minutes' => $workedMinutes,
            'overtime_hours' => $overtimeHours,
        ];
    }

    private function updatePayrollTotals(Payroll $payroll): float
    {
        $payroll->refresh();

        $totals = $payroll->items()
            ->selectRaw(
                'sum(case when item_type = ? then amount else 0 end) as earnings, sum(case when item_type = ? then amount else 0 end) as deductions',
                [PayrollItem::TYPE_EARNING, PayrollItem::TYPE_DEDUCTION]
            )
            ->first();

        $earnings = (float) ($totals->earnings ?? 0);
        $deductions = (float) ($totals->deductions ?? 0);

        $finalSalary = $this->calculateFinalSalary(
            (float) $payroll->bonus + $earnings,
            (float) $payroll->deductions + $deductions
        );

        $payroll->forceFill([
            'final_salary' => $finalSalary,
        ])->save();

        return $finalSalary;
    }

    private function calculateOvertimePay(float $baseSalary, int $overtimeHours): float
    {
        if ($baseSalary <= 0 || $overtimeHours <= 0) {
            return 0.0;
        }

        $hourlyRate = $this->calculateHourlyRate($baseSalary);

        return round($hourlyRate * $overtimeHours * 2, 2);
    }

    private function calculateFinalSalary(float $bonus, float $deductions): float
    {
        return round(max(0,  $bonus - $deductions), 2);
    }
}
