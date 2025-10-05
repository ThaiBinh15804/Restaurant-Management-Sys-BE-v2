<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shift\ShiftQueryRequest;
use App\Http\Requests\Shift\ShiftStoreRequest;
use App\Http\Requests\Shift\ShiftUpdateRequest;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Put;

/**
 * @OA\Tag(
 *     name="Shifts",
 *     description="API Endpoints for Shift Management"
 * )
 */
#[Prefix('shifts')]
class ShiftController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/shifts",
     *     tags={"Shifts"},
     *     summary="List shifts",
     *     description="Retrieve a paginated list of shifts with optional time filters",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer", default=15, maximum=100)),
     *     @OA\Parameter(name="name", in="query", description="Filter by shift name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="start_time_from", in="query", description="Filter shifts starting after or at this time (HH:MM)", @OA\Schema(type="string", pattern="^\\d{2}:\\d{2}$")),
     *     @OA\Parameter(name="start_time_to", in="query", description="Filter shifts starting before or at this time (HH:MM)", @OA\Schema(type="string", pattern="^\\d{2}:\\d{2}$")),
     *     @OA\Parameter(name="end_time_from", in="query", description="Filter shifts ending after or at this time (HH:MM)", @OA\Schema(type="string", pattern="^\\d{2}:\\d{2}$")),
     *     @OA\Parameter(name="end_time_to", in="query", description="Filter shifts ending before or at this time (HH:MM)", @OA\Schema(type="string", pattern="^\\d{2}:\\d{2}$")),
     *     @OA\Response(
     *         response=200,
     *         description="Shifts retrieved successfully"
     *     )
     * )
     */
    #[Get('/', middleware: 'permission:shifts.view')]
    public function index(ShiftQueryRequest $request): JsonResponse
    {
        $filters = $request->filters();
        $query = Shift::query()->orderBy('start_time');

        if (!empty($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['start_time_from'])) {
            $query->whereTime('start_time', '>=', $filters['start_time_from']);
        }

        if (!empty($filters['start_time_to'])) {
            $query->whereTime('start_time', '<=', $filters['start_time_to']);
        }

        if (!empty($filters['end_time_from'])) {
            $query->whereTime('end_time', '>=', $filters['end_time_from']);
        }

        if (!empty($filters['end_time_to'])) {
            $query->whereTime('end_time', '<=', $filters['end_time_to']);
        }

        $perpage = $request->perPage();
        $paginator = $query->paginate($perpage);
        
        return $this->successResponse($paginator, 'Shifts retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/shifts",
     *     tags={"Shifts"},
     *     summary="Create shift",
     *     description="Create a new shift by providing name and working timeframe",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","start_time","end_time"},
     *             @OA\Property(property="name", type="string", example="Morning Shift"),
     *             @OA\Property(property="start_time", type="string", example="08:00"),
     *             @OA\Property(property="end_time", type="string", example="16:00")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Shift created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    #[Post('/', middleware: 'permission:shifts.create')]
    public function store(ShiftStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['start_time'] = $this->formatTimeForStorage($data['start_time']);
        $data['end_time'] = $this->formatTimeForStorage($data['end_time']);

        $shift = Shift::create($data);

        return $this->successResponse($shift, 'Shift created successfully', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/shifts/{id}",
     *     tags={"Shifts"},
     *     summary="Show shift",
     *     description="Retrieve details for a specific shift",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Shift ID", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Shift retrieved successfully"),
     *     @OA\Response(response=404, description="Shift not found")
     * )
     */
    #[Get('/{id}', middleware: 'permission:shifts.view')]
    public function show(string $id): JsonResponse
    {
        $shift = Shift::find($id);

        if (!$shift) {
            return $this->errorResponse('Shift not found', [], 404);
        }

        return $this->successResponse($shift, 'Shift retrieved successfully');
    }

    /**
     * @OA\Put(
     *     path="/api/shifts/{id}",
     *     tags={"Shifts"},
     *     summary="Update shift",
     *     description="Update an existing shift's details",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Shift ID", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Morning Shift"),
     *             @OA\Property(property="start_time", type="string", example="08:00"),
     *             @OA\Property(property="end_time", type="string", example="17:00")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Shift updated successfully"),
     *     @OA\Response(response=404, description="Shift not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    #[Put('/{id}', middleware: 'permission:shifts.edit')]
    public function update(ShiftUpdateRequest $request, string $id): JsonResponse
    {
        $shift = Shift::find($id);

        if (!$shift) {
            return $this->errorResponse('Shift not found', [], 404);
        }

        $data = $request->validated();

        if (isset($data['start_time'])) {
            $data['start_time'] = $this->formatTimeForStorage($data['start_time']);
        }

        if (isset($data['end_time'])) {
            $data['end_time'] = $this->formatTimeForStorage($data['end_time']);
        }

        $startTime = $data['start_time'] ?? $shift->start_time?->format('H:i');
        $endTime = $data['end_time'] ?? $shift->end_time?->format('H:i');

        if ($startTime && $endTime && !$this->isEndTimeAfterStart($startTime, $endTime)) {
            return $this->errorResponse('The end time must be after the start time.', [], 422);
        }

        $shift->update($data);

        return $this->successResponse($shift->fresh(), 'Shift updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/shifts/{id}",
     *     tags={"Shifts"},
     *     summary="Delete shift",
     *     description="Remove a shift if it's not assigned to employees",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Shift ID", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Shift deleted successfully"),
     *     @OA\Response(response=400, description="Shift is assigned to employees"),
     *     @OA\Response(response=404, description="Shift not found")
     * )
     */
    #[Delete('/{id}', middleware: 'permission:shifts.delete')]
    public function destroy(string $id): JsonResponse
    {
        $shift = Shift::find($id);

        if (!$shift) {
            return $this->errorResponse('Shift not found', [], 404);
        }

        if ($shift->employeeAssignments()->exists()) {
            return $this->errorResponse('Cannot delete shift assigned to employees.', [], 400);
        }

        $shift->delete();

        return $this->successResponse([], 'Shift deleted successfully');
    }

    private function formatTimeForStorage(string $time): string
    {
    $time = substr($time, 0, 5);

    return Carbon::createFromFormat('H:i', $time)->format('H:i:s');
    }

    private function isEndTimeAfterStart(string $start, string $end): bool
    {
        $startCarbon = Carbon::createFromFormat('H:i', substr($start, 0, 5));
        $endCarbon = Carbon::createFromFormat('H:i', substr($end, 0, 5));

        return $endCarbon->greaterThan($startCarbon);
    }
}
