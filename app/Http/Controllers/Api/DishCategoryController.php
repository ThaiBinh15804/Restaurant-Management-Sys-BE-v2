<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DishCategory;
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
 *     name="DishCategories",
 *     description="API Endpoints for Dish Category Management"
 * )
 */
#[Prefix('auth/dish-categories')]
#[Middleware('auth:api')]
class DishCategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/auth/dish-categories",
     *     tags={"DishCategories"},
     *     summary="Get all dish categories",
     *     description="Retrieve all dish categories with pagination and optional search by name",
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
     *         name="name",
     *         in="query",
     *         description="Search by category name (partial match)",
     *         required=false,
     *         @OA\Schema(type="string", example="đồ ăn hàn")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Dish categories retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Dish categories retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="string", example="DC001"),
     *                         @OA\Property(property="name", type="string", example="Món chính"),
     *                         @OA\Property(property="desc", type="string", example="Các món ăn chính"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-10-03T12:00:00"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-10-03T12:00:00"),
     *                         @OA\Property(property="dishes_count", type="integer", example=8)
     *                     )
     *                 ),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=50)
     *             )
     *         )
     *     )
     * )
     */
   
    #[Get('/', middleware: ['permission:table-sessions.view'])]
    public function index(Request $request): JsonResponse
    {
        $page  = max((int) $request->get('page', 1), 1);
        $limit = min((int) $request->get('limit', 5), 100);

        $query = DishCategory::withCount('dishes');

        // Nếu có query name thì lọc theo tên
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->get('name') . '%');
        }

        if ($request->filled('desc')) {
            $query->where('desc', 'like', '%' . $request->get('desc') . '%');
        }

        // Lấy danh mục + đếm số lượng món
        $categories = $query->orderBy('created_at', 'desc')
            ->paginate(
                perPage: $limit,
                page: $page
            );

        return $this->successResponse(
            $categories,
            'Dish categories retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/auth/dish-categories",
     *     tags={"DishCategories"},
     *     summary="Create new dish category",
     *     description="Create a new dish category",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Khai vị"),
     *             @OA\Property(property="desc", type="string", example="Các món khai vị"),
     *         )
     *     ),
     *     @OA\Response(response=201, description="Dish category created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    #[Post('/', middleware: ['permission:table-sessions.create'])]
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:dish_categories,name',
            'desc' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $category = DishCategory::create($request->all());

        return $this->successResponse($category, 'Dish category created successfully', 201);
    }

    /**
     * @OA\Put(
     *     path="/api/auth/dish-categories/{id}",
     *     tags={"DishCategories"},
     *     summary="Update dish category",
     *     description="Update an existing dish category",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Dish Category ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Món chính"),
     *             @OA\Property(property="desc", type="string", example="Các món chính")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Dish category updated successfully"),
     *     @OA\Response(response=404, description="Dish category not found")
     * )
     */
    #[Put('/{id}', middleware: ['permission:table-sessions.edit'])]
    public function update(Request $request, string $id): JsonResponse
    {
        $category = DishCategory::find($id);

        if (!$category) {
            return $this->errorResponse('Dish category not found', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255|unique:dish_categories,name,' . $id,
            'desc' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $category->update($request->all());

        return $this->successResponse($category, 'Dish category updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/auth/dish-categories/{id}",
     *     tags={"DishCategories"},
     *     summary="Delete dish category",
     *     description="Delete a dish category. If any dishes are linked, deletion is prevented.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Dish Category ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Dish category deleted successfully"),
     *     @OA\Response(response=400, description="Cannot delete category with linked dishes"),
     *     @OA\Response(response=404, description="Dish category not found")
     * )
     */
    #[Delete('/{id}', middleware: ['permission:table-sessions.delete'])]
    public function destroy(string $id): JsonResponse
    {
        $category = DishCategory::withCount('dishes')->find($id);

        if (!$category) {
            return $this->errorResponse('Dish category not found', [], 404);
        }

        if ($category->dishes_count > 0) {
            return $this->errorResponse(
                'Cannot delete category because there are dishes linked to it',
                [],
                400
            );
        }

        $category->delete();

        return $this->successResponse([], 'Dish category deleted successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/auth/dish-categories/get-name-list-dish-category",
     *     tags={"DishCategories"},
     *     summary="Lấy danh sách tên danh mục món ăn",
     *     description="Trả về danh sách {id, name} của các danh mục món ăn",
     *     operationId="getNameListDishCategory",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách tên danh mục món ăn",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Món khai vị")
     *             )
     *         )
     *     )
     * )
     */
    #[Get('/get-name-list-dish-category', middleware: ['permission:table-sessions.view'])]
    public function getListNameDishCategory(Request $request): JsonResponse
    {
        // Lấy danh sách id + name danh mục món ăn
        $categories = DishCategory::orderBy('created_at', 'desc')
            ->select('id', 'name')
            ->get();

        return $this->successResponse(
            $categories,
            'Get name dish categories retrieved successfully'
        );
    }
}
