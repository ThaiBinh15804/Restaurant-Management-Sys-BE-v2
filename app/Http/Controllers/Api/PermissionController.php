<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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
 *     name="Permissions",
 *     description="API Endpoints for Permission Management"
 * )
 */
#[Prefix('permissions')]
#[Middleware('auth:api')]
class PermissionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/permissions",
     *     tags={"Permissions"},
     *     summary="Get all permissions",
     *     description="Retrieve all permissions with pagination",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search permissions by name or code",
     *         required=false,
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
     *                 type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(type="object",
     *               @OA\Property(property="id", type="string", example="P001"),
     *               @OA\Property(property="name", type="string", example="Manage Users"),
     *               @OA\Property(property="code", type="string", example="users.manage"),
     *               @OA\Property(property="description", type="string", example="Can manage user accounts"),
     *               @OA\Property(property="is_active", type="boolean", example=true))),
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    #[Get('/')]
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->get('per_page', 15), 100);
        $search = $request->get('search');
        
        $query = Permission::query();
        
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('code', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }
        
        $permissions = $query->orderBy('name')->paginate($perPage);

        return $this->successResponse(
            $permissions,
            'Permissions retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/permissions/{id}",
     *     tags={"Permissions"},
     *     summary="Get permission by ID",
     *     description="Retrieve a specific permission by ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Permission ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permission retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Permission retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *               @OA\Property(property="id", type="string", example="P001"),
     *               @OA\Property(property="name", type="string", example="Manage Users"),
     *               @OA\Property(property="code", type="string", example="users.manage"),
     *               @OA\Property(property="description", type="string", example="Can manage user accounts"),
     *               @OA\Property(property="is_active", type="boolean", example=true))
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Permission not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Permission not found"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    #[Get('/{id}')]
    public function show(string $id): JsonResponse
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return $this->errorResponse(
                'Permission not found',
                [],
                404
            );
        }

        return $this->successResponse(
            $permission,
            'Permission retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/permissions",
     *     tags={"Permissions"},
     *     summary="Create new permission",
     *     description="Create a new permission",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","code","description"},
     *             @OA\Property(property="name", type="string", example="manage_products"),
     *             @OA\Property(property="code", type="string", example="manage_products"),
     *             @OA\Property(property="description", type="string", example="Can manage products"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Permission created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Permission created successfully"),
     *             @OA\Property(property="data", type="object",
     *               @OA\Property(property="id", type="string", example="P001"),
     *               @OA\Property(property="name", type="string", example="Manage Users"),
     *               @OA\Property(property="code", type="string", example="users.manage"),
     *               @OA\Property(property="description", type="string", example="Can manage user accounts"),
     *               @OA\Property(property="is_active", type="boolean", example=true))
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    #[Post('/')]
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:100|unique:permissions,code',
            'description' => 'required|string|max:255',
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation failed',
                $validator->errors(),
                422
            );
        }

        $permissionData = $request->all();
        $permissionData['is_active'] = $permissionData['is_active'] ?? true;

        $permission = Permission::create($permissionData);

        return $this->successResponse(
            $permission,
            'Permission created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/permissions/{id}",
     *     tags={"Permissions"},
     *     summary="Update permission",
     *     description="Update an existing permission",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Permission ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="manage_products"),
     *             @OA\Property(property="code", type="string", example="manage_products"),
     *             @OA\Property(property="description", type="string", example="Can manage products"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permission updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Permission updated successfully"),
     *             @OA\Property(property="data", type="object",
     *               @OA\Property(property="id", type="string", example="P001"),
     *               @OA\Property(property="name", type="string", example="Manage Users"),
     *               @OA\Property(property="code", type="string", example="users.manage"),
     *               @OA\Property(property="description", type="string", example="Can manage user accounts"),
     *               @OA\Property(property="is_active", type="boolean", example=true))
     *         )
     *     )
     * )
     */
    #[Put('/{id}')]
    public function update(Request $request, string $id): JsonResponse
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return $this->errorResponse(
                'Permission not found',
                [],
                404
            );
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'code' => 'sometimes|string|max:100|unique:permissions,code,' . $id,
            'description' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation failed',
                $validator->errors(),
                422
            );
        }

        $permission->update($request->all());

        return $this->successResponse(
            $permission,
            'Permission updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/permissions/{id}",
     *     tags={"Permissions"},
     *     summary="Delete permission",
     *     description="Delete a permission",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Permission ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permission deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Permission deleted successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    #[Delete('/{id}')]
    public function destroy(string $id): JsonResponse
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return $this->errorResponse(
                'Permission not found',
                [],
                404
            );
        }

        // Check if permission is being used by any roles
        if ($permission->roles()->count() > 0) {
            return $this->errorResponse(
                'Permission cannot be deleted as it is assigned to one or more roles',
                [],
                400
            );
        }

        $permission->delete();

        return $this->successResponse(
            [],
            'Permission deleted successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/permissions/{id}/roles",
     *     tags={"Permissions"},
     *     summary="Get roles assigned to permission",
     *     description="Get all roles that have this permission",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Permission ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Roles retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Roles retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object",
     *               @OA\Property(property="id", type="string", example="R001"),
     *               @OA\Property(property="name", type="string", example="Admin"),
     *               @OA\Property(property="description", type="string", example="System administrator"),
     *               @OA\Property(property="is_active", type="boolean", example=true)))
     *         )
     *     )
     * )
     */
    #[Get('/{id}/roles')]
    public function roles(string $id): JsonResponse
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return $this->errorResponse(
                'Permission not found',
                [],
                404
            );
        }

        $roles = $permission->roles()->get();

        return $this->successResponse(
            $roles,
            'Roles retrieved successfully'
        );
    }
}