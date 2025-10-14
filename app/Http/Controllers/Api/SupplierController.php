<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\SupplierQueryRequest;
use App\Http\Requests\Supplier\SupplierStatusRequest;
use App\Http\Requests\Supplier\SupplierStoreRequest;
use App\Http\Requests\Supplier\SupplierUpdateRequest;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
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
 *     name="Suppliers",
 *     description="API Endpoints for Supplier Management"
 * )
 */
#[Prefix('suppliers')]
class SupplierController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/suppliers",
     *     tags={"Suppliers"},
     *     summary="List suppliers",
     *     description="Retrieve a paginated list of suppliers with optional filters",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="name", in="query", description="Filter by supplier name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="email", in="query", description="Filter by email", @OA\Schema(type="string")),
     *     @OA\Parameter(name="phone", in="query", description="Filter by phone", @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_active", in="query", description="Filter by active status", @OA\Schema(type="boolean")),
     *     @OA\Response(
     *         response=200,
     *         description="Suppliers retrieved successfully"
     *     )
     * )
     */
    #[Get('/', middleware: 'permission:suppliers.view')]
    public function index(SupplierQueryRequest $request): JsonResponse
    {
        $filters = $request->filters();

        $query = Supplier::query()
            ->orderBy('name')
            ->when(
                $filters['name'] ?? null,
                fn($q, $v) => $q->where('name', 'like', '%' . addcslashes($v, '%_') . '%')
            )
            ->when(
                $filters['email'] ?? null,
                fn($q, $v) => $q->where('email', 'like', '%' . addcslashes($v, '%_') . '%')
            )
            ->when(
                $filters['phone'] ?? null,
                fn($q, $v) => $q->where('phone', 'like', '%' . addcslashes($v, '%_') . '%')
            )
            ->when(array_key_exists('is_active', $filters), function ($q) use ($filters) {
                $isActive = filter_var($filters['is_active'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                if ($isActive !== null) {
                    $q->where('is_active', $isActive);
                }
            });

        $perPage = $request->perPage();
        $paginator = $query->paginate($perPage);

        return $this->successResponse($paginator, 'Suppliers retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/suppliers",
     *     tags={"Suppliers"},
     *     summary="Create supplier",
     *     description="Create a new supplier",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Fresh Food Suppliers Co."),
     *             @OA\Property(property="phone", type="string", example="0901234567"),
     *             @OA\Property(property="contact_person_name", type="string", example="John Doe"),
     *             @OA\Property(property="contact_person_phone", type="string", example="0901234567"),
     *             @OA\Property(property="email", type="string", format="email", example="contact@freshfood.com"),
     *             @OA\Property(property="address", type="string", example="123 Main Street, City"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Supplier created successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    #[Post('/', middleware: 'permission:suppliers.create')]
    public function store(SupplierStoreRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['created_by'] = auth('api')->id();
            $data['updated_by'] = auth('api')->id();

            $supplier = Supplier::create($data);

            Log::info('Supplier created successfully', [
                'supplier_id' => $supplier->id,
                'created_by' => auth('api')->id(),
            ]);

            return $this->successResponse(
                $supplier,
                'Supplier created successfully',
                201
            );
        } catch (\Exception $e) {
            Log::error('Failed to create supplier', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'Failed to create supplier: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/suppliers/{id}",
     *     tags={"Suppliers"},
     *     summary="Get supplier details",
     *     description="Retrieve detailed information about a specific supplier by ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Supplier ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Supplier retrieved successfully"),
     *     @OA\Response(response=404, description="Supplier not found")
     * )
     */
    #[Get('/{id}', middleware: 'permission:suppliers.view')]
    public function show(string $id): JsonResponse
    {
        $supplier = Supplier::with(['stockImports'])->find($id);

        if (!$supplier) {
            return $this->errorResponse('Supplier not found', [], 404);
        }

        return $this->successResponse($supplier, 'Supplier retrieved successfully');
    }

    /**
     * @OA\Put(
     *     path="/api/suppliers/{id}",
     *     tags={"Suppliers"},
     *     summary="Update supplier",
     *     description="Update supplier information",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Supplier ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Fresh Food Suppliers Co."),
     *             @OA\Property(property="phone", type="string", example="0901234567"),
     *             @OA\Property(property="contact_person_name", type="string", example="John Doe"),
     *             @OA\Property(property="contact_person_phone", type="string", example="0901234567"),
     *             @OA\Property(property="email", type="string", format="email", example="contact@freshfood.com"),
     *             @OA\Property(property="address", type="string", example="123 Main Street, City"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Supplier updated successfully"),
     *     @OA\Response(response=404, description="Supplier not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    #[Put('/{id}', middleware: 'permission:suppliers.edit')]
    public function update(SupplierUpdateRequest $request, string $id): JsonResponse
    {
        try {
            $supplier = Supplier::find($id);

            if (!$supplier) {
                return $this->errorResponse('Supplier not found', [], 404);
            }

            $data = $request->validated();
            $data['updated_by'] = auth('api')->id();

            $supplier->update($data);

            Log::info('Supplier updated successfully', [
                'supplier_id' => $supplier->id,
                'updated_by' => auth('api')->id(),
            ]);

            return $this->successResponse(
                $supplier->fresh(),
                'Supplier updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to update supplier', [
                'supplier_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to update supplier: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/suppliers/{id}/activate",
     *     tags={"Suppliers"},
     *     summary="Activate or deactivate supplier",
     *     description="Toggle the active status of a supplier",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Supplier ID",
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
     *     @OA\Response(response=200, description="Supplier status updated successfully"),
     *     @OA\Response(response=404, description="Supplier not found")
     * )
     */
    #[Patch('/{id}/activate', middleware: 'permission:suppliers.edit')]
    public function toggle(SupplierStatusRequest $request, string $id): JsonResponse
    {
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return $this->errorResponse('Supplier not found', [], 404);
        }

        $supplier->is_active = $request->boolean('is_active');
        $supplier->updated_by = auth('api')->id();
        $supplier->save();

        return $this->successResponse($supplier->fresh(), 'Supplier status updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/suppliers/{id}",
     *     tags={"Suppliers"},
     *     summary="Delete supplier",
     *     description="Delete a supplier by ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Supplier ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Supplier deleted successfully"),
     *     @OA\Response(response=404, description="Supplier not found"),
     *     @OA\Response(response=409, description="Cannot delete supplier with existing stock imports")
     * )
     */
    #[Delete('/{id}', middleware: 'permission:suppliers.delete')]
    public function destroy(string $id): JsonResponse
    {
        try {
            $supplier = Supplier::find($id);

            if (!$supplier) {
                return $this->errorResponse('Supplier not found', [], 404);
            }

            // Check if supplier has stock imports
            if ($supplier->stockImports()->exists()) {
                return $this->errorResponse(
                    'Cannot delete supplier with existing stock imports. Please deactivate instead.',
                    [],
                    409
                );
            }

            $supplier->delete();

            Log::info('Supplier deleted', ['supplier_id' => $id]);

            return $this->successResponse([], 'Supplier deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete supplier', [
                'supplier_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to delete supplier: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/suppliers/active/list",
     *     tags={"Suppliers"},
     *     summary="Get active suppliers",
     *     description="Retrieve list of active suppliers",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Active suppliers retrieved successfully")
     * )
     */
    #[Get('/active/list', middleware: 'permission:suppliers.view')]
    public function activeSuppliers(): JsonResponse
    {
        $suppliers = Supplier::where('is_active', true)
            ->orderBy('name')
            ->get();

        return $this->successResponse(
            $suppliers,
            'Active suppliers retrieved successfully'
        );
    }
}
