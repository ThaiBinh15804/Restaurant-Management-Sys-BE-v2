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
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        $query = Payroll::query()
            ->with(['employee', 'paidByEmployee'])
            ->orderByDesc('year')
            ->orderByDesc('month');

        $filters = $request->filters();

        if (!empty($filters['month'])) {
            $query->where('month', $filters['month']);
        }

        if (!empty($filters['year'])) {
            $query->where('year', $filters['year']);
        }

        if (array_key_exists('status', $filters) && $filters['status'] !== null) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        $paginator = $query->paginate($request->perPage(), ['*'], 'page', $request->page());
        $paginator->withQueryString();

        return $this->successResponse([
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Payrolls retrieved successfully');
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

        DB::transaction(function () use ($employees, $month, $year, $overwrite, &$created, &$skipped) {
            foreach ($employees as $employee) {
                $existing = Payroll::where('employee_id', $employee->id)
                    ->where('month', $month)
                    ->where('year', $year)
                    ->first();

                if ($existing && !$overwrite) {
                    $skipped[] = $employee->id;
                    continue;
                }

                $overtimeHours = EmployeeShift::where('employee_id', $employee->id)
                    ->whereYear('assigned_date', $year)
                    ->whereMonth('assigned_date', $month)
                    ->sum('overtime_hours');

                $baseSalary = (float) ($employee->base_salary ?? 0);
                $overtimePay = $this->calculateOvertimePay($baseSalary, (int) $overtimeHours);

                $payload = [
                    'month' => $month,
                    'year' => $year,
                    'base_salary' => $baseSalary,
                    'bonus' => $overtimePay,
                    'deductions' => 0,
                    'final_salary' => $baseSalary + $overtimePay,
                    'status' => Payroll::STATUS_DRAFT,
                    'payment_method' => Payroll::PAYMENT_CASH,
                    'payment_ref' => null,
                    'paid_at' => null,
                    'notes' => null,
                ];

                if ($existing) {
                    $existing->update($payload);
                    $created[] = $existing->id;
                } else {
                    $payload['employee_id'] = $employee->id;
                    $payroll = Payroll::create($payload);
                    $created[] = $payroll->id;
                }
            }
        });

        return $this->successResponse([
            'generated' => $created,
            'skipped' => $skipped,
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

    private function calculateOvertimePay(float $baseSalary, int $overtimeHours): float
    {
        if ($overtimeHours <= 0) {
            return 0.0;
        }

        $hourlyRate = $baseSalary / 160; // Approximate monthly hours

        return round($hourlyRate * $overtimeHours, 2);
    }

    private function calculateFinalSalary(float $baseSalary, float $bonus, float $deductions): float
    {
        return round(max(0, $baseSalary + $bonus - $deductions), 2);
    }
}
