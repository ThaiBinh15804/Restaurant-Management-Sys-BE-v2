<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
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
 *     name="Users",
 *     description="API Endpoints for User Management"
 * )
 */
#[Prefix('users')]
#[Middleware('auth:api')]
class UserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/users",
     *     tags={"Users"},
     *     summary="Get all users",
     *     description="Retrieve all users with pagination",
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
     *     @OA\Response(
     *         response=200,
     *         description="Users retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Users retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="string", example="U001"),
     *                         @OA\Property(property="username", type="string", example="john_doe"),
     *                         @OA\Property(property="email", type="string", example="john@example.com"),
     *                         @OA\Property(property="status", type="integer", example=1)
     *                     )
     *                 ),
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    #[Get('/', middleware: ['permission:users.view'])]
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->get('per_page', 15), 100);

        $users = User::with('role')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->successResponse(
            $users,
            'Users retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     tags={"Users"},
     *     summary="Get user by ID",
     *     description="Retrieve a specific user by ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="User retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *               @OA\Property(property="id", type="string", example="U001"),
     *               @OA\Property(property="username", type="string", example="john_doe"),
     *               @OA\Property(property="email", type="string", example="john@example.com"),
     *               @OA\Property(property="status", type="integer", example=1))
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="User not found"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    #[Get('/{id}', middleware: ['permission:users.view'])]
    public function show(string $id): JsonResponse
    {
        $user = User::with('role')->find($id);

        if (!$user) {
            return $this->errorResponse(
                'User not found',
                [],
                404
            );
        }

        return $this->successResponse(
            $user,
            'User retrieved successfully'
        );
    }


    // Create user through API of Customer or Employee. User must belong to Role Customer or Employee.
    // /**
    //  * @OA\Post(
    //  *     path="/api/users",
    //  *     tags={"Users"},
    //  *     summary="Create new user",
    //  *     description="Create a new user",
    //  *     security={{"bearerAuth":{}}},
    //  *     @OA\RequestBody(
    //  *         required=true,
    //  *         @OA\JsonContent(
    //  *             required={"name","email","password","role_id"},
    //  *             @OA\Property(property="name", type="string", example="John Doe"),
    //  *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
    //  *             @OA\Property(property="password", type="string", format="password", example="password123"),
    //  *             @OA\Property(property="role_id", type="string", example="ROLE123"),
    //  *             @OA\Property(property="status", type="integer", example=1, description="User status (1=active, 0=inactive)")
    //  *         )
    //  *     ),
    //  *     @OA\Response(
    //  *         response=201,
    //  *         description="User created successfully",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="status", type="string", example="success"),
    //  *             @OA\Property(property="message", type="string", example="User created successfully"),
    //  *             @OA\Property(property="data", type="object",
    //  *               @OA\Property(property="id", type="string", example="U001"),
    //  *               @OA\Property(property="username", type="string", example="john_doe"),
    //  *               @OA\Property(property="email", type="string", example="john@example.com"),
    //  *               @OA\Property(property="status", type="integer", example=1))
    //  *         )
    //  *     ),
    //  *     @OA\Response(
    //  *         response=422,
    //  *         description="Validation error",
    //  *         @OA\JsonContent(
    //  *             @OA\Property(property="status", type="string", example="error"),
    //  *             @OA\Property(property="message", type="string", example="Validation failed"),
    //  *             @OA\Property(property="errors", type="object")
    //  *         )
    //  *     )
    //  * )
    //  */
    // #[Post('/', middleware: ['permission:users.create'])]
    // public function store(Request $request): JsonResponse
    // {
    //     $validator = Validator::make($request->all(), [
    //         'name' => 'required|string|max:100',
    //         'email' => 'required|email|unique:users,email|max:100',
    //         'password' => 'required|string|min:6|max:255',
    //         'role_id' => 'required|string|exists:roles,id',
    //         'status' => 'sometimes|integer|in:0,1'
    //     ]);

    //     if ($validator->fails()) {
    //         return $this->errorResponse(
    //             'Validation failed',
    //             $validator->errors(),
    //             422
    //         );
    //     }

    //     $userData = $request->all();
    //     $userData['password'] = bcrypt($userData['password']);
    //     $userData['status'] = $userData['status'] ?? User::STATUS_ACTIVE;

    //     $user = User::create($userData);
    //     $user->load('role');

    //     return $this->successResponse(
    //         $user,
    //         'User created successfully',
    //         201
    //     );
    // }

    /**
     * @OA\Put(
     *     path="/api/users/{id}",
     *     tags={"Users"},
     *     summary="Update user",
     *     description="Update an existing user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="role_id", type="string", example="ROLE123"),
     *             @OA\Property(property="status", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="User updated successfully"),
     *             @OA\Property(property="data", type="object",
     *               @OA\Property(property="id", type="string", example="U001"),
     *               @OA\Property(property="username", type="string", example="john_doe"),
     *               @OA\Property(property="email", type="string", example="john@example.com"),
     *               @OA\Property(property="status", type="integer", example=1))
     *         )
     *     )
     * )
     */
    #[Put('/{id}', middleware: ['permission:users.edit'])]
    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return $this->errorResponse(
                'User not found',
                [],
                404
            );
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:users,email,' . $id . '|max:100',
            'password' => 'sometimes|string|min:6|max:255',
            'role_id' => 'sometimes|string|exists:roles,id',
            'status' => 'sometimes|integer|in:0,1'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation failed',
                $validator->errors(),
                422
            );
        }

        $userData = $request->all();

        if (isset($userData['password'])) {
            $userData['password'] = bcrypt($userData['password']);
        }

        $user->update($userData);
        $user->load('role');

        return $this->successResponse(
            $user,
            'User updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/users/{id}",
     *     tags={"Users"},
     *     summary="Delete user",
     *     description="Delete a user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="User deleted successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    #[Delete('/{id}', middleware: ['permission:users.delete'])]
    public function destroy(string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return $this->errorResponse(
                'User not found',
                [],
                404
            );
        }

        $user->delete();

        return $this->successResponse(
            [],
            'User deleted successfully'
        );
    }
}
