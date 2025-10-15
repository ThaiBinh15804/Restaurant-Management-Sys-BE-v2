<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngredientCategory\IngredientCategoryQueryRequest;
use App\Http\Requests\IngredientCategory\IngredientCategoryStatusRequest;
use App\Http\Requests\IngredientCategory\IngredientCategoryStoreRequest;
use App\Http\Requests\IngredientCategory\IngredientCategoryUpdateRequest;
use App\Models\IngredientCategory;
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
 *     name="Ingredient Categories",
 *     description="API Endpoints for Ingredient Category Management"
 * )
 */
#[Prefix('ingredient-categories')]
class IngredientCategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/ingredient-categories",
     *     tags={"Ingredient Categories"},
     *     summary="Lấy danh sách danh mục nguyên liệu",
     *     description="Trả về danh sách danh mục nguyên liệu có phân trang và tìm kiếm",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Số trang", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", description="Số bản ghi mỗi trang", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", description="Tìm kiếm theo tên", @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_active", in="query", description="Lọc theo trạng thái", @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="Danh sách danh mục lấy thành công")
     * )
     */
    #[Get('/', middleware: 'permission:ingredient_categories.view')]
    public function index(IngredientCategoryQueryRequest $request): JsonResponse
    {
        try {
            $query = IngredientCategory::query()->withCount('ingredients')->with('ingredients');

            // Apply filters from request
            $query = $request->applyFilters($query);

            // Sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->input('per_page', 15);
            $categories = $query->paginate($perPage);

            return $this->successResponse($categories, 'Lấy danh sách danh mục nguyên liệu thành công');
        } catch (\Exception $e) {
            Log::error('Error fetching ingredient categories: ' . $e->getMessage());
            return $this->errorResponse('Có lỗi xảy ra khi lấy danh sách danh mục nguyên liệu', [], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/ingredient-categories",
     *     tags={"Ingredient Categories"},
     *     summary="Tạo danh mục nguyên liệu mới",
     *     description="Tạo một danh mục nguyên liệu mới trong hệ thống",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Rau củ"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Tạo danh mục thành công"),
     *     @OA\Response(response=422, description="Lỗi validation")
     * )
     */
    #[Post('/', middleware: 'permission:ingredient_categories.create')]
    public function store(IngredientCategoryStoreRequest $request): JsonResponse
    {
        try {
            $category = IngredientCategory::create([
                'name' => $request->name,
                'is_active' => $request->input('is_active', true),
            ]);

            Log::info('Ingredient category created successfully', [
                'category_id' => $category->id,
                'created_by' => auth('api')->id(),
            ]);

            return $this->successResponse($category, 'Tạo danh mục nguyên liệu thành công', 201);
        } catch (\Exception $e) {
            Log::error('Error creating ingredient category: ' . $e->getMessage());
            return $this->errorResponse('Có lỗi xảy ra khi tạo danh mục nguyên liệu: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/ingredient-categories/{id}",
     *     tags={"Ingredient Categories"},
     *     summary="Lấy chi tiết danh mục nguyên liệu",
     *     description="Trả về thông tin chi tiết của một danh mục nguyên liệu",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", description="ID danh mục", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Lấy thông tin danh mục thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy danh mục")
     * )
     */
    #[Get('/{id}', middleware: 'permission:ingredient_categories.view')]
    public function show(string $id): JsonResponse
    {
        try {
            $category = IngredientCategory::with(['ingredients' => function ($query) {
                $query->select('id', 'ingredient_category_id', 'name', 'unit', 'current_stock', 'is_active');
            }])->findOrFail($id);

            return $this->successResponse($category, 'Lấy thông tin danh mục nguyên liệu thành công');
        } catch (\Exception $e) {
            Log::error('Error fetching ingredient category: ' . $e->getMessage());
            return $this->errorResponse('Không tìm thấy danh mục nguyên liệu', [], 404);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/ingredient-categories/{id}",
     *     tags={"Ingredient Categories"},
     *     summary="Cập nhật danh mục nguyên liệu",
     *     description="Cập nhật thông tin danh mục nguyên liệu theo ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", description="ID danh mục", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Rau củ quả"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cập nhật danh mục thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy danh mục"),
     *     @OA\Response(response=422, description="Lỗi validation")
     * )
     */
    #[Put('/{id}', middleware: 'permission:ingredient_categories.edit')]
    public function update(IngredientCategoryUpdateRequest $request, string $id): JsonResponse
    {
        try {
            $category = IngredientCategory::findOrFail($id);

            $category->update([
                'name' => $request->name,
                'is_active' => $request->input('is_active', $category->is_active),
            ]);

            Log::info('Ingredient category updated successfully', [
                'category_id' => $category->id,
                'updated_by' => auth('api')->id(),
            ]);

            return $this->successResponse($category->fresh(), 'Cập nhật danh mục nguyên liệu thành công');
        } catch (\Exception $e) {
            Log::error('Error updating ingredient category: ' . $e->getMessage());
            return $this->errorResponse('Có lỗi xảy ra khi cập nhật danh mục nguyên liệu: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/ingredient-categories/{id}/activate",
     *     tags={"Ingredient Categories"},
     *     summary="Bật/tắt trạng thái danh mục nguyên liệu",
     *     description="Thay đổi trạng thái hoạt động của danh mục nguyên liệu",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", description="ID danh mục", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"is_active"},
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cập nhật trạng thái thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy danh mục")
     * )
     */
    #[Patch('/{id}/activate', middleware: 'permission:ingredient_categories.edit')]
    public function toggle(IngredientCategoryStatusRequest $request, string $id): JsonResponse
    {
        try {
            $category = IngredientCategory::findOrFail($id);
            $category->update(['is_active' => $request->is_active]);

            return $this->successResponse($category->fresh(), 'Cập nhật trạng thái danh mục nguyên liệu thành công');
        } catch (\Exception $e) {
            Log::error('Error toggling ingredient category status: ' . $e->getMessage());
            return $this->errorResponse('Có lỗi xảy ra khi cập nhật trạng thái danh mục nguyên liệu', [], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/ingredient-categories/{id}",
     *     tags={"Ingredient Categories"},
     *     summary="Xóa danh mục nguyên liệu",
     *     description="Xóa danh mục nguyên liệu theo ID (chỉ xóa nếu không có nguyên liệu nào thuộc danh mục này)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", description="ID danh mục", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Xóa danh mục thành công"),
     *     @OA\Response(response=400, description="Không thể xóa do có ràng buộc"),
     *     @OA\Response(response=404, description="Không tìm thấy danh mục")
     * )
     */
    #[Delete('/{id}', middleware: 'permission:ingredient_categories.delete')]
    public function destroy(string $id): JsonResponse
    {
        try {
            $category = IngredientCategory::findOrFail($id);

            // Check if category has ingredients
            if ($category->ingredients()->exists()) {
                return $this->errorResponse('Không thể xóa danh mục này vì vẫn còn nguyên liệu thuộc danh mục', [], 400);
            }

            $category->delete();

            Log::info('Ingredient category deleted', ['category_id' => $id]);

            return $this->successResponse([], 'Xóa danh mục nguyên liệu thành công');
        } catch (\Exception $e) {
            Log::error('Error deleting ingredient category: ' . $e->getMessage());
            return $this->errorResponse('Có lỗi xảy ra khi xóa danh mục nguyên liệu', [], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/ingredient-categories/active/list",
     *     tags={"Ingredient Categories"},
     *     summary="Lấy danh sách danh mục nguyên liệu đang hoạt động",
     *     description="Trả về tất cả các danh mục nguyên liệu có trạng thái is_active = true",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Lấy danh sách thành công")
     * )
     */
    #[Get('/active/list', middleware: 'permission:ingredient_categories.view')]
    public function activeCategories(): JsonResponse
    {
        try {
            $categories = IngredientCategory::where('is_active', true)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            return $this->successResponse($categories, 'Lấy danh sách danh mục nguyên liệu hoạt động thành công');
        } catch (\Exception $e) {
            Log::error('Error fetching active ingredient categories: ' . $e->getMessage());
            return $this->errorResponse('Có lỗi xảy ra khi lấy danh sách danh mục nguyên liệu hoạt động', [], 500);
        }
    }
}
