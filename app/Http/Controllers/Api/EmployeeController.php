<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\EmployeeQueryRequest;
use App\Http\Requests\Employee\EmployeeStatusRequest;
use App\Http\Requests\Employee\EmployeeStoreRequest;
use App\Http\Requests\Employee\EmployeeUpdateRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
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
        $filters = $request->filters();

        $query = Employee::with('user')
            ->orderBy('full_name')
            ->when(
                $filters['full_name'] ?? null,
                fn($q, $v) =>
                $q->where('full_name', 'like', '%' . addcslashes($v, '%_') . '%')
            )
            ->when(array_key_exists('is_active', $filters), function ($q) use ($filters) {
                $isActive = filter_var($filters['is_active'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                if ($isActive !== null) {
                    $q->where('is_active', $isActive);
                }
            })
            ->when(
                $filters['contract_type'] ?? null,
                fn($q, $v) =>
                $q->where('contract_type', $v)
            )
            ->when(
                $filters['gender'] ?? null,
                fn($q, $v) =>
                $q->where('gender', $v)
            )
            ->when(
                $filters['hire_date_from'] ?? null,
                fn($q, $v) =>
                $q->whereDate('hire_date', '>=', $v)
            )
            ->when(
                $filters['hire_date_to'] ?? null,
                fn($q, $v) =>
                $q->whereDate('hire_date', '<=', $v)
            );

        $perPage = $request->perPage();
        $paginator = $query->paginate($perPage);

        return $this->successResponse($paginator, 'Employees retrieved successfully');
    }


    /**
     * @OA\Post(
     *     path="/api/employees",
     *     tags={"Employees"},
     *     summary="Create employee with user account",
     *     description="Create a new employee along with their user account for login",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"full_name","contract_type","base_salary","email","password","password_confirmation","role_id"},
     *             @OA\Property(property="full_name", type="string", example="John Smith"),
     *             @OA\Property(property="phone", type="string", example="0123456789"),
     *             @OA\Property(property="gender", type="string", example="male"),
     *             @OA\Property(property="address", type="string", example="123 Main St"),
     *             @OA\Property(property="bank_account", type="string", example="1234567890"),
     *             @OA\Property(property="contract_type", type="integer", enum={0, 1}, example=0, description="0: Full-time, 1: Part-time"),
     *             @OA\Property(property="base_salary", type="number", format="float", example=2000.00),
     *             @OA\Property(property="hire_date", type="string", format="date", example="2025-01-01"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="email", type="string", format="email", example="john.smith@restaurant.com", description="User account email"),
     *             @OA\Property(property="password", type="string", format="password", example="password123", description="User account password (min 8 chars)"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123"),
     *             @OA\Property(property="role_id", type="string", example="R-123456", description="Role ID for the user account")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Employee created successfully with user account"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    #[Post('/', middleware: 'permission:employees.create')]
    public function store(EmployeeStoreRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $employee = DB::transaction(function () use ($data) {
                if (!empty($data['user_id'])) {
                    $userId = $data['user_id'];
                } else {
                    $user = User::create([
                        'email' => $data['email'],
                        'password' => Hash::make($data['password']),
                        'role_id' => $data['role_id'],
                        'status' => User::STATUS_ACTIVE,
                        'created_by' => auth('api')->id(),
                    ]);
                    $userId = $user->id;
                }

                $employeeData = [
                    'full_name' => $data['full_name'],
                    'phone' => $data['phone'] ?? null,
                    'gender' => $data['gender'] ?? null,
                    'address' => $data['address'] ?? null,
                    'bank_account' => $data['bank_account'] ?? null,
                    'contract_type' => $data['contract_type'],
                    'base_salary' => $data['base_salary'],
                    'hire_date' => $data['hire_date'] ?? null,
                    'is_active' => $data['is_active'] ?? true,
                    'user_id' => $userId,
                ];

                $employee = Employee::create($employeeData);

                return $employee;
            });

            Log::info('Employee created successfully', [
                'employee_id' => $employee->id,
                'user_id' => $employee->user_id,
                'created_by' => auth('api')->id(),
            ]);

            return $this->successResponse(
                $employee->fresh(['user']),
                'Employee created successfully with user account',
                201
            );
        } catch (\Exception $e) {
            Log::error('Failed to create employee', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'Failed to create employee: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/employees/{id}",
     *     tags={"Employees"},
     *     summary="Get employee details",
     *     description="Retrieve detailed information about a specific employee by ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Employee ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
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
     *     summary="Update employee and user account",
     *     description="Update employee information and optionally update their user account (email, password, role)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Employee ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="full_name", type="string", example="John Smith Updated"),
     *             @OA\Property(property="phone", type="string", example="0123456789"),
     *             @OA\Property(property="gender", type="string", example="male|female|other"),
     *             @OA\Property(property="address", type="string", example="123 Main St"),
     *             @OA\Property(property="bank_account", type="string", example="1234567890"),
     *             @OA\Property(property="contract_type", type="integer", enum={0, 1}, example=0, description="0: Full-time, 1: Part-time"),
     *             @OA\Property(property="base_salary", type="number", format="float", example=2500.00),
     *             @OA\Property(property="hire_date", type="string", format="date", example="2025-01-01"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *
     *             @OA\Property(property="email", type="string", format="email", example="john.updated@restaurant.com", description="Update user email"),
     *             @OA\Property(property="password", type="string", format="password", example="newpassword123", description="Update user password (optional)"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="newpassword123"),
     *             @OA\Property(property="role_id", type="string", example="R-123456", description="Update user role")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Employee updated successfully"),
     *     @OA\Response(response=404, description="Employee not found")
     * )
     */
    #[Put('/{id}', middleware: 'permission:employees.edit')]
    public function update(EmployeeUpdateRequest $request, string $id): JsonResponse
    {
        try {
            $employee = Employee::with('user')->find($id);

            if (!$employee) {
                return $this->errorResponse('Employee not found', [], 404);
            }

            $data = $request->validated();

            DB::transaction(function () use ($employee, $data) {
                $employeeData = array_filter($data, function ($key) {
                    return !in_array($key, ['email', 'password', 'password_confirmation', 'role_id']);
                }, ARRAY_FILTER_USE_KEY);

                $employee->fill($employeeData);
                $employee->save();

                if ($employee->user && (isset($data['email']) || isset($data['password']) || isset($data['role_id']))) {
                    $userUpdates = [];

                    if (isset($data['email'])) {
                        $userUpdates['email'] = $data['email'];
                    }

                    if (!empty($data['password'])) {
                        $userUpdates['password'] = Hash::make($data['password']);
                    }

                    if (isset($data['role_id'])) {
                        $userUpdates['role_id'] = $data['role_id'];
                    }

                    if (!empty($userUpdates)) {
                        $userUpdates['updated_by'] = auth('api')->id();
                        $employee->user->update($userUpdates);
                    }
                }
            });

            Log::info('Employee updated successfully', [
                'employee_id' => $employee->id,
                'updated_by' => auth('api')->id(),
            ]);

            return $this->successResponse(
                $employee->fresh(['user']),
                'Employee updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to update employee', [
                'employee_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to update employee: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/employees/{id}/activate",
     *     tags={"Employees"},
     *     summary="Activate or deactivate employee",
     *     description="Toggle the active status of an employee",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Employee ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"is_active"},
     *             @OA\Property(property="is_active", type="boolean", example=true, description="Set to true to activate, false to deactivate")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Employee status updated successfully"),
     *     @OA\Response(response=404, description="Employee not found")
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
     *     description="Delete an employee by ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Employee ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Employee deleted successfully"),
     *     @OA\Response(response=404, description="Employee not found")
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

    /**
     * @OA\Get(
     *     path="/api/employees/chefs",
     *     tags={"Employees"},
     *     summary="Lấy danh sách nhân viên là chef",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Danh sách chef")
     * )
     */
    #[Get('/find/chefs', middleware: 'permission:employees.view')]
    public function chefs(): JsonResponse
    {
        $chefs = Employee::whereHas('user.role', function ($q) {
            $q->where('name', 'Kitchen Staff');
        })
        ->where('is_active', true)
        ->with(['user.role'])
        ->get();

        if ($chefs->isEmpty()) {
            return $this->errorResponse(
                'Không tìm thấy nhân viên nào có vai trò Kitchen Staff',
                [],
                404
            );
        }

        return $this->successResponse($chefs, 'Danh sách chef');
    }
}
