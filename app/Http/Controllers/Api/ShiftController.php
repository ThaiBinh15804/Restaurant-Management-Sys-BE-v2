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
#[Middleware('auth:api')]
class ShiftController extends Controller
{
    #[Get('/', middleware: 'permission:shifts.view')]
    public function index(ShiftQueryRequest $request): JsonResponse
    {
        $query = Shift::query()->orderBy('start_time');
        $filters = $request->filters();

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
        ], 'Shifts retrieved successfully');
    }

    #[Post('/', middleware: 'permission:shifts.create')]
    public function store(ShiftStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['start_time'] = $this->formatTimeForStorage($data['start_time']);
        $data['end_time'] = $this->formatTimeForStorage($data['end_time']);

        $shift = Shift::create($data);

        return $this->successResponse($shift, 'Shift created successfully', 201);
    }

    #[Get('/{id}', middleware: 'permission:shifts.view')]
    public function show(string $id): JsonResponse
    {
        $shift = Shift::find($id);

        if (!$shift) {
            return $this->errorResponse('Shift not found', [], 404);
        }

        return $this->successResponse($shift, 'Shift retrieved successfully');
    }

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
