<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Models\Customer;
use App\Traits\HasFileUpload;
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
    use HasFileUpload;
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
        $perPage = $request->get('per_page');

        $users = User::with(['role', 'customerProfile', 'employeeProfile'])
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
        $user = User::with(['role', 'customerProfile', 'employeeProfile'])->find($id);

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

    /**
     * @OA\Post(
     *   path="/api/auth/profile/avatar",
     *   tags={"Profile"},
     *   summary="Cập nhật ảnh đại diện",
     *   @OA\Response(
     *     response=200,
     *     description="Cập nhật ảnh đại diện thành công"
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error"
     *   )
     * )
     */
    #[Post('/avatar')]
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $user = $request->user();
        if (!$user) {
            return $this->errorResponse('Unauthenticated.', [], 401);
        }

        $path = $request->file('avatar');
        if ($request->hasFile('avatar')) {
            $entityType = $this->getEntityTypeFromController();

            $avatarPath = $this->uploadFile(
                $request->file('avatar'),
                $entityType,
                $user->id
            );

            $user->update(['avatar' => $avatarPath]);
        }
        $user->save();

        return $this->successResponse(['avatar' => $user->avatar], 'Cập nhật ảnh đại diện thành công');
    }

    /**
     * @OA\Post(
     *   path="/api/auth/profile/change-password",
     *   tags={"Profile"},
     *   summary="Đổi mật khẩu",
     *   @OA\Response(
     *     response=200,
     *     description="Đổi mật khẩu thành công"
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error"
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated"
     *   )
     * )
     */
    #[Post('/changePassword')]
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password'      => 'required|string|min:6',
            'new_password'          => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return $this->errorResponse('Mật khẩu hiện tại không đúng', 422);
        }

        $user->password = Hash::make($validated['new_password']);
        $user->save();

        try {
            Mail::raw('Bạn vừa đổi mật khẩu thành công.', function ($m) use ($user) {
                $m->to($user->email)->subject('Đổi mật khẩu thành công');
            });
        } catch (\Throwable $e) {
            Log::error('Gửi email thất bại: ' . $e->getMessage());
        }

        return $this->successResponse(null, 'Đổi mật khẩu thành công');
    }

    /**
     * @OA\Put(
     *     path="/api/users/show/my-profile",
     *     tags={"Profile"},
     *     summary="Update customer profile",
     *     description="Customer update their own profile",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="email", type="string", example="john@example.com"),
     *             @OA\Property(property="full_name", type="string", example="John Michael Doe"),
     *             @OA\Property(property="phone", type="string", example="0123456789"),
     *             @OA\Property(property="gender", type="string", enum={"male", "female", "other"}, example="male"),
     *             @OA\Property(property="address", type="string", example="123 Main St, City")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully"
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    #[Put('/show/my-profile')]
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'email'     => 'sometimes|email|unique:users,email,' . $user->id . '|max:100',
            'full_name' => 'sometimes|nullable|string|max:100',
            'phone'     => 'sometimes|nullable|string|max:30',
            'gender'    => 'sometimes|nullable|string|in:Nam,Nữ,Khác',
            'address'   => 'sometimes|nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        // Update user email if provided
        if ($request->has('email')) {
            $user->email = $request->email;
            $user->save();
        }

        // Update customer profile
        $customerPayload = array_filter(
            $request->only(['full_name', 'phone', 'gender', 'address']),
            fn($v) => $v !== null
        );

        if (!empty($customerPayload)) {
            $customer = $user->customerProfile ?: Customer::firstOrCreate(['user_id' => $user->id]);
            $customer->fill($customerPayload)->save();
            $user->setRelation('customerProfile', $customer);
        }

        $user->load(['role', 'customerProfile', 'employeeProfile']);

        return $this->successResponse($user, 'Profile updated successfully');
    }
}
