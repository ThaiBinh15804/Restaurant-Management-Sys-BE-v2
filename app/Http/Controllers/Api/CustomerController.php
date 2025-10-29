<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CustomerQueryRequest;
use App\Http\Requests\Customer\CustomerStatusRequest;
use App\Http\Requests\Customer\CustomerStoreRequest;
use App\Http\Requests\Customer\CustomerUpdateRequest;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Traits\HasFileUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
 *     name="Customers",
 *     description="API Endpoints for Customer Management"
 * )
 */
#[Prefix('customers')]
class CustomerController extends Controller
{
    use HasFileUpload;
    /**
     * @OA\Get(
     *     path="/api/customers",
     *     tags={"Customers"},
     *     summary="List customers",
     *     description="Retrieve a paginated list of customers with optional filters",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="full_name", in="query", description="Filter by customer name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="email", in="query", description="Filter by email", @OA\Schema(type="string")),
     *     @OA\Parameter(name="phone", in="query", description="Filter by phone number", @OA\Schema(type="string")),
     *     @OA\Parameter(name="gender", in="query", description="Filter by gender", @OA\Schema(type="string", enum={"male", "female", "other"})),
     *     @OA\Parameter(name="membership_level", in="query", description="Filter by membership level (1-4)", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="user_id", in="query", description="Filter by user ID", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Customers retrieved successfully"
     *     )
     * )
     */
    #[Get('/', middleware: 'permission:customers.view')]
    public function index(CustomerQueryRequest $request): JsonResponse
    {
        $filters = $request->filters();
        $query = Customer::with('user')
            ->orderBy('full_name')
            ->when($filters['full_name'] ?? null, fn($q, $v) => $q->where('full_name', 'like', "%$v%"))
            ->when($filters['email'] ?? null, fn($q, $v) => $q->whereHas('user', fn($query) => $query->where('email', 'like', "%$v%")))
            ->when($filters['phone'] ?? null, fn($q, $v) => $q->where('phone', 'like', "%$v%"))
            ->when($filters['gender'] ?? null, fn($q, $v) => $q->where('gender', $v))
            ->when(isset($filters['membership_level']), fn($q) => $q->where('membership_level', $filters['membership_level']))
            ->when($filters['user_id'] ?? null, fn($q, $v) => $q->where('user_id', $v));

        $perPage = $request->perPage();
        $paginator = $query->paginate($perPage);

        Log::info('Customers listed', [
            'filters' => $filters,
            'page' => $request->query('page', 1),
            'per_page' => $perPage,
        ]);

        return $this->successResponse($paginator, 'Customers retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/customers/{id}",
     *     tags={"Customers"},
     *     summary="Get customer by ID",
     *     description="Retrieve a specific customer with their user information",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Customer ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Customer retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Customer not found"
     *     )
     * )
     */
    #[Get('/{id}', middleware: 'permission:customers.view')]
    public function show(string $id): JsonResponse
    {
        $customer = Customer::with(['user'])->find($id);

        if (!$customer) {
            return $this->errorResponse(
                'Customer not found',
                [],
                404
            );
        }

        return $this->successResponse($customer, 'Customer retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/customers/{id}",
     *     tags={"Customers"},
     *     summary="Update customer and user account",
     *     description="Update customer profile and optionally update their user avatar. Note: Use POST method with _method=PUT for file upload support.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Customer ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="full_name", type="string", example="", description="Full name of the customer"),
     *                 @OA\Property(property="phone", type="string", example="", description="Customer phone number"),
     *                 @OA\Property(property="gender", type="string", enum={"male", "female", "other"}, example="", description="Gender: male, female, or other"),
     *                 @OA\Property(property="address", type="string", example="", description="Customer address"),
     *                 @OA\Property(property="membership_level", type="integer", example="", description="1=Bronze, 2=Silver, 3=Gold, 4=Titanium"),
     *                 @OA\Property(property="avatar", type="string", format="binary", example="", description="Avatar image file (jpeg, jpg, png, gif, webp, max 2MB)"),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Customer updated successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Customer not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    #[Post('/{id}', middleware: 'permission:customers.edit')]
    public function update(CustomerUpdateRequest $request, string $id): JsonResponse
    {
        try {
            $customer = Customer::with('user')->find($id);

            if (!$customer) {
                return $this->errorResponse(
                    'Customer not found',
                    [],
                    404
                );
            }

            $data = $request->validated();

            DB::transaction(function () use ($customer, $data, $request) {
                $customerData = collect($data)
                    ->except(['avatar']) 
                    ->filter(function ($value) {
                        return !is_null($value);
                    })
                    ->toArray();

                $customer->fill($customerData);
                $customer->save();

                if ($request->hasFile('avatar') && $customer->user) {
                    $entityType = $this->getEntityTypeFromController();
                    $oldAvatar = $customer->user->avatar;

                    $avatarPath = $this->uploadFile(
                        $request->file('avatar'),
                        $entityType,
                        $customer->id,
                        $oldAvatar
                    );

                    $customer->user->update([
                        'avatar' => $avatarPath,
                        'updated_by' => auth('api')->id(),
                    ]);
                }
            });

            $customer->load('user');

            Log::info('Customer updated successfully', [
                'customer_id' => $customer->id,
                'updated_by' => auth('api')->id(),
            ]);

            return $this->successResponse(
                $customer,
                'Customer updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to update customer', [
                'customer_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'Failed to update customer: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/customers/{id}/membership",
     *     tags={"Customers"},
     *     summary="Update customer membership level",
     *     description="Update the membership level of a customer",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Customer ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"membership_level"},
     *             @OA\Property(property="membership_level", type="integer", example=3, description="1=Bronze, 2=Silver, 3=Gold, 4=Titanium")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Customer membership level updated successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Customer not found"
     *     )
     * )
     */
    #[Patch('/{id}/membership', middleware: 'permission:customers.edit')]
    public function updateMembership(CustomerStatusRequest $request, string $id): JsonResponse
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return $this->errorResponse(
                'Customer not found',
                [],
                404
            );
        }

        try {
            $customer->update([
                'membership_level' => $request->validated()['membership_level'],
            ]);

            Log::info('Customer membership level updated', [
                'customer_id' => $customer->id,
                'membership_level' => $customer->membership_level,
            ]);

            return $this->successResponse(
                $customer,
                'Customer membership level updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to update customer membership level', [
                'customer_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to update customer membership level',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/customers/{id}",
     *     tags={"Customers"},
     *     summary="Delete customer",
     *     description="Delete a customer profile",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Customer ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Customer deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Customer not found"
     *     )
     * )
     */
    #[Delete('/{id}', middleware: 'permission:customers.delete')]
    public function destroy(string $id): JsonResponse
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return $this->errorResponse(
                'Customer not found',
                [],
                404
            );
        }

        $user = $customer->user;

        if ($user) {
            Log::info('Deleting associated user account', [
                'user_id' => $user->id,
                'customer_id' => $customer->id,
            ]);
            $user->delete();
        }

        try {
            $customer->delete();

            Log::info('Customer deleted', [
                'customer_id' => $id,
            ]);

            return $this->successResponse(
                [],
                'Customer deleted successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to delete customer', [
                'customer_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to delete customer',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}
