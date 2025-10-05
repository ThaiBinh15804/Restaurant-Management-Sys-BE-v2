<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeShift\EmployeeShiftCheckInRequest;
use App\Http\Requests\EmployeeShift\EmployeeShiftCheckOutRequest;
use App\Http\Requests\EmployeeShift\EmployeeShiftQueryRequest;
use App\Http\Requests\EmployeeShift\EmployeeShiftStatusRequest;
use App\Http\Requests\EmployeeShift\EmployeeShiftStoreRequest;
use App\Models\EmployeeShift;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Patch;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;

/**
 * @OA\Tag(
 *     name="Employee Shifts",
 *     description="API Endpoints for Employee Shift Assignments"
 * )
 */
#[Prefix('employee-shifts')]
class EmployeeShiftController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/employee-shifts",
     *     tags={"Employee Shifts"},
     *     summary="List employee shifts",
     *     description="Retrieve a paginated list of employee shift assignments with optional filters",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer", default=15, maximum=100)),
     *     @OA\Parameter(name="employee_id", in="query", description="Filter by employee ID", @OA\Schema(type="string")),
     *     @OA\Parameter(name="shift_id", in="query", description="Filter by shift ID", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", description="Filter by status", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="date_from", in="query", description="Filter assignments from date", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", description="Filter assignments to date", @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Employee shifts retrieved successfully")
     * )
     */
    #[Get('/', middleware: 'permission:employee_shifts.view')]
    public function index(EmployeeShiftQueryRequest $request): JsonResponse
    {
        $filters = $request->filters();

        $query = EmployeeShift::with(['employee', 'shift'])
            ->orderByDesc('assigned_date')
            ->orderBy('shift_id')
            ->when(
                $filters['employee_id'] ?? null,
                fn($q, $v) =>
                $q->where('employee_id', $v)
            )
            ->when(
                $filters['shift_id'] ?? null,
                fn($q, $v) =>
                $q->where('shift_id', $v)
            )
            ->when(
                array_key_exists('status', $filters) && $filters['status'] !== null,
                fn($q) =>
                $q->where('status', $filters['status'])
            )
            ->when(
                $filters['date_from'] ?? null,
                fn($q, $v) =>
                $q->whereDate('assigned_date', '>=', $v)
            )
            ->when(
                $filters['date_to'] ?? null,
                fn($q, $v) =>
                $q->whereDate('assigned_date', '<=', $v)
            );

        $perPage = $request->perPage();
        $paginator = $query->paginate($perPage);

        return $this->successResponse($paginator, 'Employee shifts retrieved successfully');
    }


    /**
     * @OA\Post(
     *     path="/api/employee-shifts",
     *     tags={"Employee Shifts"},
     *     summary="Assign shift",
     *     description="Assign a shift to an employee on a given date",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id","shift_id","assigned_date"},
     *             @OA\Property(property="employee_id", type="string", example="EMP001"),
     *             @OA\Property(property="shift_id", type="string", example="SH001"),
     *             @OA\Property(property="assigned_date", type="string", format="date", example="2025-09-01"),
     *             @OA\Property(property="status", type="integer", example=0, description="Initial status"),
     *             @OA\Property(property="notes", type="string", example="Assigned for breakfast prep")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Employee shift assigned successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    #[Post('/', middleware: 'permission:employee_shifts.create')]
    public function store(EmployeeShiftStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? EmployeeShift::STATUS_SCHEDULED;

        $exists = EmployeeShift::where('employee_id', $data['employee_id'])
            ->where('shift_id', $data['shift_id'])
            ->whereDate('assigned_date', $data['assigned_date'])
            ->exists();

        if ($exists) {
            return $this->errorResponse('Shift assignment already exists for this employee on the selected date.', [], 422);
        }

        $assignment = EmployeeShift::create($data);

        return $this->successResponse(
            $assignment->load(['employee', 'shift']),
            'Employee shift assigned successfully',
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/employee-shifts/{id}",
     *     tags={"Employee Shifts"},
     *     summary="Show employee shift",
     *     description="Retrieve details of a specific employee shift assignment",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", description="Employee shift ID", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Employee shift retrieved successfully"),
     *     @OA\Response(response=404, description="Employee shift not found")
     * )
     */
    #[Get('/{id}', middleware: 'permission:employee_shifts.view')]
    public function show(string $id): JsonResponse
    {
        $assignment = EmployeeShift::with(['employee', 'shift'])->find($id);

        if (!$assignment) {
            return $this->errorResponse('Employee shift not found', [], 404);
        }

        return $this->successResponse($assignment, 'Employee shift retrieved successfully');
    }

    /**
     * @OA\Patch(
     *     path="/api/employee-shifts/{id}/check-in",
     *     tags={"Employee Shifts"},
     *     summary="Record employee check-in",
     *     description="Mark an employee as checked-in for a shift",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Employee shift ID", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="check_in", type="string", format="date-time", example="2025-09-01 08:05:00"),
     *             @OA\Property(property="notes", type="string", example="Arrived slightly late due to traffic")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Check-in recorded successfully"),
     *     @OA\Response(response=404, description="Employee shift not found")
     * )
     */
    #[Patch('/{id}/check-in', middleware: 'permission:employee_shifts.edit')]
    public function checkIn(EmployeeShiftCheckInRequest $request, string $id): JsonResponse
    {
        $assignment = EmployeeShift::find($id);

        if (!$assignment) {
            return $this->errorResponse('Employee shift not found', [], 404);
        }

        $timestamp = $request->input('check_in')
            ? Carbon::createFromFormat('Y-m-d H:i:s', $request->input('check_in'))
            : Carbon::now();

        $assignment->check_in = $timestamp;
        $assignment->status = $assignment->status === EmployeeShift::STATUS_SCHEDULED
            ? EmployeeShift::STATUS_PRESENT
            : $assignment->status;

        if ($request->filled('notes')) {
            $assignment->notes = $request->input('notes');
        }

        $assignment->save();

        Log::info('Employee shift check-in recorded', [
            'employee_shift_id' => $assignment->id,
            'check_in' => $assignment->check_in,
        ]);

        return $this->successResponse($assignment->fresh(['employee', 'shift']), 'Check-in recorded successfully');
    }

    /**
     * @OA\Patch(
     *     path="/api/employee-shifts/{id}/check-out",
     *     tags={"Employee Shifts"},
     *     summary="Record employee check-out",
     *     description="Mark an employee as checked-out from a shift and optionally record overtime",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Employee shift ID", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="check_out", type="string", format="date-time", example="2025-09-01 16:15:00"),
     *             @OA\Property(property="overtime_hours", type="integer", example=1),
     *             @OA\Property(property="notes", type="string", example="Stayed late to clean kitchen")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Check-out recorded successfully"),
     *     @OA\Response(response=404, description="Employee shift not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    #[Patch('/{id}/check-out', middleware: 'permission:employee_shifts.edit')]
    public function checkOut(EmployeeShiftCheckOutRequest $request, string $id): JsonResponse
    {
        $assignment = EmployeeShift::with('shift')->find($id);

        if (!$assignment) {
            return $this->errorResponse('Employee shift not found', [], 404);
        }

        $timestamp = $request->input('check_out')
            ? Carbon::createFromFormat('Y-m-d H:i:s', $request->input('check_out'))
            : Carbon::now();

        if ($assignment->check_in && $timestamp->lessThan($assignment->check_in)) {
            return $this->errorResponse('Check-out time cannot be before check-in time.', [], 422);
        }

        $assignment->check_out = $timestamp;

        if ($request->filled('overtime_hours')) {
            $assignment->overtime_hours = (int) $request->input('overtime_hours');
        } elseif ($assignment->shift) {
            $scheduledEnd = Carbon::parse($assignment->assigned_date . ' ' . $assignment->shift->end_time);
            $diffMinutes = $scheduledEnd->diffInMinutes($timestamp, false);
            $assignment->overtime_hours = $diffMinutes > 0 ? (int) ceil($diffMinutes / 60) : 0;
        }

        if ($request->filled('notes')) {
            $assignment->notes = $request->input('notes');
        }

        $assignment->status = $assignment->status === EmployeeShift::STATUS_SCHEDULED
            ? EmployeeShift::STATUS_PRESENT
            : $assignment->status;

        $assignment->save();

        Log::info('Employee shift check-out recorded', [
            'employee_shift_id' => $assignment->id,
            'check_out' => $assignment->check_out,
            'overtime_hours' => $assignment->overtime_hours,
        ]);

        return $this->successResponse($assignment->fresh(['employee', 'shift']), 'Check-out recorded successfully');
    }

    /**
     * @OA\Patch(
     *     path="/api/employee-shifts/{id}/status",
     *     tags={"Employee Shifts"},
     *     summary="Update employee shift status",
     *     description="Manually update the status or notes of a shift assignment",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Employee shift ID", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="notes", type="string", example="Marked as late arrival")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Employee shift status updated successfully"),
     *     @OA\Response(response=404, description="Employee shift not found")
     * )
     */
    #[Patch('/{id}/status', middleware: 'permission:employee_shifts.edit')]
    public function updateStatus(EmployeeShiftStatusRequest $request, string $id): JsonResponse
    {
        $assignment = EmployeeShift::find($id);

        if (!$assignment) {
            return $this->errorResponse('Employee shift not found', [], 404);
        }

        $assignment->status = $request->integer('status');

        if ($request->filled('notes')) {
            $assignment->notes = $request->input('notes');
        }

        $assignment->save();

        return $this->successResponse($assignment->fresh(['employee', 'shift']), 'Employee shift status updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/employee-shifts/{id}",
     *     tags={"Employee Shifts"},
     *     summary="Delete employee shift",
     *     description="Remove an employee shift assignment",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Employee shift ID", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Employee shift deleted successfully"),
     *     @OA\Response(response=404, description="Employee shift not found")
     * )
     */
    #[Delete('/{id}', middleware: 'permission:employee_shifts.delete')]
    public function destroy(string $id): JsonResponse
    {
        $assignment = EmployeeShift::find($id);

        if (!$assignment) {
            return $this->errorResponse('Employee shift not found', [], 404);
        }

        $assignment->delete();

        return $this->successResponse([], 'Employee shift deleted successfully');
    }
}
