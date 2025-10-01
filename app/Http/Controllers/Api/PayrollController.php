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
#[Middleware('auth:api')]
class PayrollController extends Controller
{
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

    #[Get('/{id}', middleware: 'permission:payrolls.view')]
    public function show(string $id): JsonResponse
    {
        $payroll = Payroll::with(['employee', 'items', 'paidByEmployee'])->find($id);

        if (!$payroll) {
            return $this->errorResponse('Payroll not found', [], 404);
        }

        return $this->successResponse($payroll, 'Payroll retrieved successfully');
    }

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
                    'payment_method' => null,
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
