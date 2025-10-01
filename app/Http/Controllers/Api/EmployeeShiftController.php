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
#[Middleware('auth:api')]
class EmployeeShiftController extends Controller
{
    #[Get('/', middleware: 'permission:employee_shifts.view')]
    public function index(EmployeeShiftQueryRequest $request): JsonResponse
    {
        $query = EmployeeShift::query()
            ->with(['employee', 'shift'])
            ->orderByDesc('assigned_date')
            ->orderBy('shift_id');

        $filters = $request->filters();

        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (!empty($filters['shift_id'])) {
            $query->where('shift_id', $filters['shift_id']);
        }

        if (array_key_exists('status', $filters) && $filters['status'] !== null) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('assigned_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('assigned_date', '<=', $filters['date_to']);
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
        ], 'Employee shifts retrieved successfully');
    }

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

    #[Get('/{id}', middleware: 'permission:employee_shifts.view')]
    public function show(string $id): JsonResponse
    {
        $assignment = EmployeeShift::with(['employee', 'shift'])->find($id);

        if (!$assignment) {
            return $this->errorResponse('Employee shift not found', [], 404);
        }

        return $this->successResponse($assignment, 'Employee shift retrieved successfully');
    }

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
