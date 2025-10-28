<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ingredient\IngredientQueryRequest;
use App\Http\Requests\Ingredient\IngredientStatusRequest;
use App\Http\Requests\Ingredient\IngredientStoreRequest;
use App\Http\Requests\Ingredient\IngredientUpdateRequest;
use App\Models\Ingredient;
use App\Traits\HasFileUpload;
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
 *     name="Ingredients",
 *     description="API Endpoints for Ingredient Management"
 * )
 */
#[Prefix('ingredients')]
class IngredientController extends Controller
{
    use HasFileUpload;

    /**
     * @OA\Get(
     *     path="/api/ingredients",
     *     tags={"Ingredients"},
     *     summary="List ingredients",
     *     description="Retrieve a paginated list of ingredients with optional filters",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="name", in="query", description="Filter by ingredient name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="unit", in="query", description="Filter by unit", @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_active", in="query", description="Filter by active status", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="low_stock", in="query", description="Filter ingredients below minimum stock", @OA\Schema(type="boolean")),
     *     @OA\Parameter(
     *         name="category_ids[]",
     *         in="query",
     *         description="Filter by one or multiple category IDs",
     *         @OA\Schema(type="array", @OA\Items(type="string")),
     *         style="form",
     *         explode=true
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Ingredients retrieved successfully"
     *     )
     * )
     */
    #[Get('/', middleware: 'permission:ingredients.view')]
    public function index(IngredientQueryRequest $request): JsonResponse
    {
        $filters = $request->filters();

        $query = Ingredient::query()->with('category')
            ->orderBy('name')
            ->when(
                $filters['name'] ?? null,
                fn($q, $v) => $q->where('name', 'like', '%' . addcslashes($v, '%_') . '%')
            )
            ->when(
                $filters['unit'] ?? null,
                fn($q, $v) => $q->where('unit', $v)
            )
            ->when(array_key_exists('is_active', $filters), function ($q) use ($filters) {
                $isActive = filter_var($filters['is_active'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                if ($isActive !== null) {
                    $q->where('is_active', $isActive);
                }
            })
            ->when(array_key_exists('low_stock', $filters), function ($q) use ($filters) {
                $lowStock = filter_var($filters['low_stock'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                if ($lowStock === true) {
                    $q->whereColumn('current_stock', '<', 'min_stock');
                }
            })
            ->when(
                !empty($filters['category_ids']),
                fn($q, $v) => $q->whereIn('ingredient_category_id', $filters['category_ids'])
            );

        $perPage = $request->perPage();
        $paginator = $query->paginate($perPage);

        return $this->successResponse($paginator, 'Ingredients retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/ingredients",
     *     tags={"Ingredients"},
     *     summary="Create ingredient",
     *     description="Create a new ingredient",
     *     security={{"bearerAuth":{}}},
    *     @OA\RequestBody(
    *         required=true,
    *         @OA\MediaType(
    *             mediaType="multipart/form-data",
    *             @OA\Schema(
    *                 required={"name","unit","min_stock"},
    *                 @OA\Property(property="ingredient_category_id", type="string", example="INGCAT-001", description="Ingredient category ID"),
    *                 @OA\Property(property="name", type="string", example="Tomato"),
    *                 @OA\Property(property="unit", type="string", example="kg"),
    *                 @OA\Property(property="current_stock", type="number", format="float", example=0),
    *                 @OA\Property(property="min_stock", type="number", format="float", example=10),
    *                 @OA\Property(property="max_stock", type="number", format="float", example=50),
    *                 @OA\Property(property="is_active", type="boolean", example=true),
    *                 @OA\Property(
    *                     property="image",
    *                     type="string",
    *                     format="binary",
    *                     description="Ingredient image file (jpeg, jpg, png, gif, webp, max 2MB)"
    *                 )
    *             )
    *         )
    *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Ingredient created successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    #[Post('/', middleware: 'permission:ingredients.create')]
    public function store(IngredientStoreRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $imageFile = $request->file('image');
            unset($data['image']);

            $userId = auth('api')->id();
            $data['created_by'] = $userId;
            $data['updated_by'] = $userId;

            $ingredient = Ingredient::create($data);

            if ($imageFile) {
                
                $imageUrl = $this->uploadFile(
                    $imageFile,
                    $this->getEntityTypeFromController(),
                    $ingredient->id
                );

                $ingredient->image = $imageUrl;
                $ingredient->updated_by = $userId;
                $ingredient->save();
            }

            $ingredient->refresh();

            Log::info('Ingredient created successfully', [
                'ingredient_id' => $ingredient->id,
                'created_by' => $userId,
                'has_image' => (bool) $ingredient->image,
            ]);

            return $this->successResponse(
                $ingredient,
                'Ingredient created successfully',
                201
            );
        } catch (\Exception $e) {
            Log::error('Failed to create ingredient', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'Failed to create ingredient: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/ingredients/{id}",
     *     tags={"Ingredients"},
     *     summary="Get ingredient details",
     *     description="Retrieve detailed information about a specific ingredient by ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Ingredient ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Ingredient retrieved successfully"),
     *     @OA\Response(response=404, description="Ingredient not found")
     * )
     */
    #[Get('/{id}', middleware: 'permission:ingredients.view')]
    public function show(string $id): JsonResponse
    {
        $ingredient = Ingredient::find($id);

        if (!$ingredient) {
            return $this->errorResponse('Ingredient not found', [], 404);
        }

        return $this->successResponse($ingredient, 'Ingredient retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/ingredients/{id}",
     *     tags={"Ingredients"},
     *     summary="Update ingredient",
     *     description="Update ingredient information",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Ingredient ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
    *     @OA\RequestBody(
    *         required=true,
    *         @OA\MediaType(
    *             mediaType="multipart/form-data",
    *             @OA\Schema(
    *                 @OA\Property(property="ingredient_category_id", type="string", example="INGCAT-001", description="Ingredient category ID"),
    *                 @OA\Property(property="name", type="string", example="Tomato"),
    *                 @OA\Property(property="unit", type="string", example="kg"),
    *                 @OA\Property(property="current_stock", type="number", format="float", example=25),
    *                 @OA\Property(property="min_stock", type="number", format="float", example=10),
    *                 @OA\Property(property="max_stock", type="number", format="float", example=50),
    *                 @OA\Property(property="is_active", type="boolean", example=true),
    *                 @OA\Property(
    *                     property="image",
    *                     type="string",
    *                     format="binary",
    *                     description="Ingredient image file (jpeg, jpg, png, gif, webp, max 2MB)"
    *                 )
    *             )
    *         )
    *     ),
     *     @OA\Response(response=200, description="Ingredient updated successfully"),
     *     @OA\Response(response=404, description="Ingredient not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    #[Post('/{id}', middleware: 'permission:ingredients.edit')]
    public function update(IngredientUpdateRequest $request, string $id): JsonResponse
    {
        try {
            $ingredient = Ingredient::find($id);

            if (!$ingredient) {
                return $this->errorResponse('Ingredient not found', [], 404);
            }

            $data = $request->validated();

            $data = collect($data)
                ->reject(fn($value, $key) => is_null($value))
                ->toArray();

            $imageFile = $request->file('image');
            unset($data['image']);

            $userId = auth('api')->id();
            $oldImage = $ingredient->image;

            if (!empty($data)) {
                $ingredient->fill($data);
            }

            $ingredient->updated_by = $userId;

            if ($imageFile) {
                $newImageUrl = $this->uploadFile(
                    $imageFile,
                    $this->getEntityTypeFromController(),
                    $ingredient->id,
                    $oldImage
                );

                $ingredient->image = $newImageUrl;
            }

            $ingredient->save();

            Log::info('Ingredient updated successfully', [
                'ingredient_id' => $ingredient->id,
                'updated_by' => $userId,
                'image_changed' => (bool) $imageFile,
            ]);

            return $this->successResponse(
                $ingredient->fresh(),
                'Ingredient updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to update ingredient', [
                'ingredient_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to update ingredient: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/ingredients/{id}/activate",
     *     tags={"Ingredients"},
     *     summary="Activate or deactivate ingredient",
     *     description="Toggle the active status of an ingredient",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Ingredient ID",
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
     *     @OA\Response(response=200, description="Ingredient status updated successfully"),
     *     @OA\Response(response=404, description="Ingredient not found")
     * )
     */
    #[Patch('/{id}/activate', middleware: 'permission:ingredients.edit')]
    public function toggle(IngredientStatusRequest $request, string $id): JsonResponse
    {
        $ingredient = Ingredient::find($id);

        if (!$ingredient) {
            return $this->errorResponse('Ingredient not found', [], 404);
        }

        $ingredient->is_active = $request->boolean('is_active');
        $ingredient->updated_by = auth('api')->id();
        $ingredient->save();

        return $this->successResponse($ingredient->fresh(), 'Ingredient status updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/ingredients/{id}",
     *     tags={"Ingredients"},
     *     summary="Delete ingredient",
     *     description="Delete an ingredient by ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Ingredient ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Ingredient deleted successfully"),
     *     @OA\Response(response=404, description="Ingredient not found"),
     *     @OA\Response(response=409, description="Cannot delete ingredient with existing stock movements")
     * )
     */
    #[Delete('/{id}', middleware: 'permission:ingredients.delete')]
    public function destroy(string $id): JsonResponse
    {
        try {
            $ingredient = Ingredient::find($id);

            if (!$ingredient) {
                return $this->errorResponse('Ingredient not found', [], 404);
            }

            // Check if ingredient has stock movements
            if ($ingredient->stockImportDetails()->exists() || 
                $ingredient->stockExportDetails()->exists() || 
                $ingredient->stockLosses()->exists()) {
                return $this->errorResponse(
                    'Không thể xóa thành phần có số lượng tồn kho hiện tại. Vui lòng hủy kích hoạt.',
                    [],
                    409
                );
            }

            $imageUrl = $ingredient->image;
            $ingredient->delete();

            if ($imageUrl) {
                $this->deleteFileByUrl($imageUrl);
            }

            Log::info('Ingredient deleted', ['ingredient_id' => $id]);

            return $this->successResponse([], 'Ingredient deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete ingredient', [
                'ingredient_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to delete ingredient: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/ingredients/low-stock/list",
     *     tags={"Ingredients"},
     *     summary="Get low stock ingredients",
     *     description="Retrieve list of ingredients below minimum stock level",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Low stock ingredients retrieved successfully")
     * )
     */
    #[Get('/low-stock/list', middleware: 'permission:ingredients.view')]
    public function lowStock(): JsonResponse
    {
        $lowStockIngredients = Ingredient::where('is_active', true)
            ->whereColumn('current_stock', '<', 'min_stock')
            ->orderBy('name')
            ->get();

        return $this->successResponse(
            $lowStockIngredients,
            'Low stock ingredients retrieved successfully'
        );
    }
}
