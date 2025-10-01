<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\EmployeeQueryRequest;
use App\Http\Requests\Employee\EmployeeStatusRequest;
use App\Http\Requests\Employee\EmployeeStoreRequest;
use App\Http\Requests\Employee\EmployeeUpdateRequest;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Patch;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Put;

/**
 * @OA\Tag(
 *     name="Employees",
 *     description="API Endpoints for Employee Management"
 * )
 */
#[Prefix('employees')]
#[Middleware('auth:api')]
class EmployeeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/employees",
     *     tags={"Employees"},
     *     summary="List employees",
     *     description="Retrieve a paginated list of employees with optional filters",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="full_name", in="query", description="Filter by employee name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_active", in="query", description="Filter by active status", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="contract_type", in="query", description="Filter by contract type", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="gender", in="query", description="Filter by gender", @OA\Schema(type="string")),
     *     @OA\Parameter(name="hire_date_from", in="query", description="Filter hire date from", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="hire_date_to", in="query", description="Filter hire date to", @OA\Schema(type="string", format="date")),
     *     @OA\Response(
     *         response=200,
     *         description="Employees retrieved successfully"
     *     )
     * )
     */
    #[Get('/', middleware: 'permission:employees.view')]
    public function index(EmployeeQueryRequest $request): JsonResponse
    {
        $query = Employee::query()
            ->with(['user'])
            ->orderBy('full_name');

        $filters = $request->filters();

        if (!empty($filters['full_name'])) {
            $query->where('full_name', 'like', '%' . $filters['full_name'] . '%');
        }

        if (array_key_exists('is_active', $filters)) {
            $isActive = filter_var($filters['is_active'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        if (array_key_exists('contract_type', $filters) && $filters['contract_type'] !== null) {
            $query->where('contract_type', $filters['contract_type']);
        }

        if (!empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        if (!empty($filters['hire_date_from'])) {
            $query->whereDate('hire_date', '>=', $filters['hire_date_from']);
        }

        if (!empty($filters['hire_date_to'])) {
            $query->whereDate('hire_date', '<=', $filters['hire_date_to']);
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
        ], 'Employees retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/employees",
     *     tags={"Employees"},
     *     summary="Create employee",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=201, description="Employee created successfully")
     * )
     */
    #[Post('/', middleware: 'permission:employees.create')]
    public function store(EmployeeStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        $employee = Employee::create($data);

        return $this->successResponse(
            $employee->fresh(['user']),
            'Employee created successfully',
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/employees/{id}",
     *     tags={"Employees"},
     *     summary="Show employee",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Employee retrieved successfully"),
     *     @OA\Response(response=404, description="Employee not found")
     * )
     */
    #[Get('/{id}', middleware: 'permission:employees.view')]
    public function show(string $id): JsonResponse
    {
        $employee = Employee::with(['user'])->find($id);

        if (!$employee) {
            return $this->errorResponse('Employee not found', [], 404);
        }

        return $this->successResponse($employee, 'Employee retrieved successfully');
    }

    /**
     * @OA\Put(
     *     path="/api/employees/{id}",
     *     tags={"Employees"},
     *     summary="Update employee",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Employee updated successfully")
     * )
     */
    #[Put('/{id}', middleware: 'permission:employees.edit')]
    public function update(EmployeeUpdateRequest $request, string $id): JsonResponse
    {
        $employee = Employee::find($id);

        if (!$employee) {
            return $this->errorResponse('Employee not found', [], 404);
        }

        $employee->fill($request->validated());
        $employee->save();

        return $this->successResponse($employee->fresh(['user']), 'Employee updated successfully');
    }

    /**
     * @OA\Patch(
     *     path="/api/employees/{id}/activate",
     *     tags={"Employees"},
     *     summary="Toggle employee active status",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Employee status updated successfully")
     * )
     */
    #[Patch('/{id}/activate', middleware: 'permission:employees.edit')]
    public function toggle(EmployeeStatusRequest $request, string $id): JsonResponse
    {
        $employee = Employee::find($id);

        if (!$employee) {
            return $this->errorResponse('Employee not found', [], 404);
        }

        $employee->is_active = $request->boolean('is_active');
        $employee->save();

        return $this->successResponse($employee->fresh(['user']), 'Employee status updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/employees/{id}",
     *     tags={"Employees"},
     *     summary="Delete employee",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Employee deleted successfully")
     * )
     */
    #[Delete('/{id}', middleware: 'permission:employees.delete')]
    public function destroy(string $id): JsonResponse
    {
        $employee = Employee::find($id);

        if (!$employee) {
            return $this->errorResponse('Employee not found', [], 404);
        }

        $employee->delete();

        Log::info('Employee deleted', ['employee_id' => $id]);

        return $this->successResponse([], 'Employee deleted successfully');
    }
}
