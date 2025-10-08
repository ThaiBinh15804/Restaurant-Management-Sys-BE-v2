<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
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
 *     name="DiningTables",
 *     description="API Endpoints for Dining Table Management"
 * )
 */
#[Prefix('auth/dining-tables')]
class DiningTableController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/auth/dining-tables",
     *     tags={"DiningTables"},
     *     summary="Get all dining tables",
     *     description="Retrieve all dining tables with pagination",
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
     *         description="Dining tables retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Dining tables retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="string", example="DT001"),
     *                         @OA\Property(property="table_number", type="integer", example=1),
     *                         @OA\Property(property="capacity", type="integer", example=4),
     *                         @OA\Property(property="is_active", type="boolean", example=true)
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
    #[Get('/', middleware: ['permission:dining-tables.view'])]
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->get('per_page', 15), 100);

        $tables = DiningTable::orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->successResponse(
            $tables,
            'Dining tables retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/auth/dining-tables/{id}",
     *     tags={"DiningTables"},
     *     summary="Get dining table by ID",
     *     description="Retrieve a specific dining table by ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Dining Table ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Dining table retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Dining table not found"
     *     )
     * )
     */
    #[Get('/{id}', middleware: ['permission:dining-tables.view'])]
    public function show(string $id): JsonResponse
    {
        $table = DiningTable::find($id);

        if (!$table) {
            return $this->errorResponse(
                'Dining table not found',
                [],
                404
            );
        }

        return $this->successResponse(
            $table,
            'Dining table retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/auth/dining-tables",
     *     tags={"DiningTables"},
     *     summary="Create new dining table",
     *     description="Create a new dining table",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"table_number","capacity"},
     *             @OA\Property(property="table_number", type="integer", example=5),
     *             @OA\Property(property="capacity", type="integer", example=6),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Dining table created successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    #[Post('/', middleware: ['permission:dining-tables.create'])]
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'table_number' => 'required|integer|unique:dining_tables,table_number',
            'capacity' => 'required|integer|min:1',
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation failed',
                $validator->errors(),
                422
            );
        }

        $table = DiningTable::create($request->all());

        return $this->successResponse(
            $table,
            'Dining table created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/auth/dining-tables/{id}",
     *     tags={"DiningTables"},
     *     summary="Update dining table",
     *     description="Update an existing dining table",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Dining Table ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="table_number", type="integer", example=10),
     *             @OA\Property(property="capacity", type="integer", example=8),
     *             @OA\Property(property="is_active", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Dining table updated successfully"
     *     )
     * )
     */
    #[Put('/{id}', middleware: ['permission:dining-tables.edit'])]
    public function update(Request $request, string $id): JsonResponse
    {
        $table = DiningTable::find($id);

        if (!$table) {
            return $this->errorResponse(
                'Dining table not found',
                [],
                404
            );
        }

        $validator = Validator::make($request->all(), [
            'table_number' => 'sometimes|integer|unique:dining_tables,table_number,' . $id,
            'capacity' => 'sometimes|integer|min:1',
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                'Validation failed',
                $validator->errors(),
                422
            );
        }

        $table->update($request->all());

        return $this->successResponse(
            $table,
            'Dining table updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/auth/dining-tables/{id}",
     *     tags={"DiningTables"},
     *     summary="Delete dining table",
     *     description="Delete a dining table",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Dining Table ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Dining table deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Dining table not found"
     *     )
     * )
     */
    #[Delete('/{id}', middleware: ['permission:dining-tables.delete'])]
    public function destroy(string $id): JsonResponse
    {
        $table = DiningTable::find($id);

        if (!$table) {
            return $this->errorResponse(
                'Dining table not found',
                [],
                404
            );
        }

        $table->delete();

        return $this->successResponse(
            [],
            'Dining table deleted successfully'
        );
    }
}
