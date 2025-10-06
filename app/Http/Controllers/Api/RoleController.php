<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\RoleQueryRequest;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Put;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Prefix;

/**
 * @OA\Tag(
 *     name="Roles",
 *     description="API Endpoints for Role Management"
 * )
 */
#[Prefix('roles')]
class RoleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/roles",
     *     tags={"Roles"},
     *     summary="List roles",
     *     description="Retrieve a paginated list of roles with optional filters",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="name", in="query", description="Filter by role name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_active", in="query", description="Filter by active status", @OA\Schema(type="boolean")),
     *     @OA\Response(
     *         response=200,
     *         description="Roles retrieved successfully"
     *     )
     * )
     */
    #[Get('/', middleware: 'permission:roles.view')]
    public function index(RoleQueryRequest $request): JsonResponse
    {
        $query = Role::query()
            ->with('permissions')
            ->orderBy('name');

        $filters = $request->filters();

        if (!empty($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (array_key_exists('is_active', $filters)) {
            $isActive = filter_var($filters['is_active'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
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
        ], 'Roles retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/roles/{id}",
     *     tags={"Roles"},
     *     summary="Get role by ID",
     *     description="Retrieve a specific role with permissions",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Role ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Role retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", example="R001"),
     *                 @OA\Property(property="name", type="string", example="Admin"),
     *                 @OA\Property(property="description", type="string", example="System administrator"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="permissions", type="array", @OA\Items(type="object",
     *                     @OA\Property(property="id", type="string", example="P001"),
     *                     @OA\Property(property="name", type="string", example="Manage Users"),
     *                     @OA\Property(property="code", type="string", example="users.manage")
     *                 ))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Role not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Role not found"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    #[Get('/{id}', middleware: 'permission:roles.view')]
    public function show(string $id): JsonResponse
    {
        $role = Role::with('permissions')->find($id);

        if (!$role) {
            return $this->errorResponse(
                'Role not found',
                [],
                404
            );
        }

        return $this->successResponse(
            $role,
            'Role retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/roles",
     *     tags={"Roles"},
     *     summary="Create new role",
     *     description="Create a new role",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","description"},
     *             @OA\Property(property="name", type="string", example="Manager"),
     *             @OA\Property(property="description", type="string", example="Restaurant Manager Role"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(
     *                 property="permissions",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"PERM1", "PERM2"},
     *                 description="Array of permission IDs to assign to role"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Role created successfully"
     *     )
     * )
     */
    #[Post('/', middleware: 'permission:roles.create')]
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:roles,name',
            'description' => 'required|string|max:255',
            'is_active' => 'sometimes|boolean',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation failed',
                $validator->errors(),
                422
            );
        }

        try {
            $roleData = $request->only(['name', 'description', 'is_active']);
            $roleData['is_active'] = $roleData['is_active'] ?? true;

            $role = Role::create($roleData);

            if ($request->has('permissions')) {
                $role->permissions()->attach($request->permissions);
            }

            $role->load('permissions');

            Log::info('Role created', [
                'role_id' => $role->id,
            ]);

            return $this->successResponse(
                $role,
                'Role created successfully',
                201
            );
        } catch (\Exception $e) {
            Log::error('Failed to create role', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'Failed to create role',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Put(
     *     path="/api/roles/{id}",
     *     tags={"Roles"},
     *     summary="Update role",
     *     description="Update an existing role",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Role ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Manager"),
     *             @OA\Property(property="description", type="string", example="Restaurant Manager Role"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(
     *                 property="permissions",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"PERM1", "PERM2"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Role updated successfully"),
     *             @OA\Property(property="data", type="object",)
     *         )
     *     )
     * )
     */
    #[Put('/{id}', middleware: 'permission:roles.edit')]
    public function update(Request $request, string $id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return $this->errorResponse(
                'Role not found',
                [],
                404
            );
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100|unique:roles,name,' . $id,
            'description' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation failed',
                $validator->errors(),
                422
            );
        }

        try {
            $roleData = $request->only(['name', 'description', 'is_active']);
            $role->update($roleData);

            // Update permissions if provided
            if ($request->has('permissions')) {
                $role->permissions()->sync($request->permissions);
            }

            $role->load('permissions');

            Log::info('Role updated', [
                'role_id' => $role->id,
            ]);

            return $this->successResponse(
                $role,
                'Role updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to update role', [
                'role_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to update role',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/roles/{id}",
     *     tags={"Roles"},
     *     summary="Delete role",
     *     description="Delete a role",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Role ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Role deleted successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    #[Delete('/{id}', middleware: 'permission:roles.delete')]
    public function destroy(string $id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return $this->errorResponse(
                'Role not found',
                [],
                404
            );
        }

        if ($role->users()->count() > 0) {
            return $this->errorResponse(
                'Cannot delete role. It is currently assigned to users.',
                [],
                400
            );
        }

        try {
            $role->permissions()->detach();
            $role->delete();

            Log::info('Role deleted', [
                'role_id' => $id,
            ]);

            return $this->successResponse(
                [],
                'Role deleted successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to delete role', [
                'role_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to delete role',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/roles/{id}/permissions",
     *     tags={"Roles"},
     *     summary="Get role permissions",
     *     description="Get all permissions assigned to a role",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Role ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permissions retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Permissions retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(type="object")
     *             )
     *         )
     *     )
     * )
     */
    #[Get('/{id}/permissions', middleware: 'permission:roles.view')]
    public function permissions(string $id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return $this->errorResponse(
                'Role not found',
                [],
                404
            );
        }

        $permissions = $role->permissions()->get();

        return $this->successResponse(
            $permissions,
            'Permissions retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/roles/{id}/permissions",
     *     tags={"Roles"},
     *     summary="Assign permissions to role",
     *     description="Assign specific permissions to a role",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Role ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"permission_ids"},
     *             @OA\Property(
     *                 property="permission_ids",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"PERM1", "PERM2"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permissions assigned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Permissions assigned successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    #[Post('/{id}/permissions', middleware: 'permission:roles.edit')]
    public function assignPermissions(Request $request, string $id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return $this->errorResponse(
                'Role not found',
                [],
                404
            );
        }

        $validator = Validator::make($request->all(), [
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation failed',
                $validator->errors(),
                422
            );
        }

        $role->permissions()->attach($request->permission_ids);
        $permissions = $role->permissions()->get();

        return $this->successResponse(
            $permissions,
            'Permissions assigned successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/roles/{id}/permissions",
     *     tags={"Roles"},
     *     summary="Remove permissions from role",
     *     description="Remove specific permissions from a role",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Role ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"permission_ids"},
     *             @OA\Property(
     *                 property="permission_ids",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"PERM1", "PERM2"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permissions removed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Permissions removed successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    #[Delete('/{id}/permissions', middleware: 'permission:roles.edit')]
    public function removePermissions(Request $request, string $id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return $this->errorResponse(
                'Role not found',
                [],
                404
            );
        }

        $validator = Validator::make($request->all(), [
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation failed',
                $validator->errors(),
                422
            );
        }

        $role->permissions()->detach($request->permission_ids);
        $permissions = $role->permissions()->get();

        return $this->successResponse(
            $permissions,
            'Permissions removed successfully'
        );
    }

    /**
     * @OA\Put(
     *     path="/api/roles/{id}/permissions/sync",
     *     tags={"Roles"},
     *     summary="Sync role permissions",
     *     description="Replace all role permissions with the provided list",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Role ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"permission_ids"},
     *             @OA\Property(
     *                 property="permission_ids",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"PERM1", "PERM2"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permissions synced successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Permissions synced successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object",))
     *         )
     *     )
     * )
     */
    #[Put('/{id}/permissions', middleware: 'permission:roles.edit')]
    public function syncPermissions(Request $request, string $id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return $this->errorResponse(
                'Role not found',
                [],
                404
            );
        }

        $validator = Validator::make($request->all(), [
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation failed',
                $validator->errors(),
                422
            );
        }

        $role->permissions()->sync($request->permission_ids);
        $permissions = $role->permissions()->get();

        return $this->successResponse(
            $permissions,
            'Permissions synced successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/roles/{id}/users",
     *     tags={"Roles"},
     *     summary="Get role users",
     *     description="Get all users assigned to a role",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Role ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Users retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Users retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object",
     *               @OA\Property(property="id", type="string", example="U001"),
     *               @OA\Property(property="username", type="string", example="john_doe"),
     *               @OA\Property(property="email", type="string", example="john@example.com"),
     *               @OA\Property(property="status", type="integer", example=1)))
     *         )
     *     )
     * )
     */
    #[Get('/{id}/users', middleware: 'permission:roles.view')]
    public function users(string $id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return $this->errorResponse(
                'Role not found',
                [],
                404
            );
        }

        $users = $role->users()->get();

        return $this->successResponse(
            $users,
            'Users retrieved successfully'
        );
    }
}
