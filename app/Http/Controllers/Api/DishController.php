<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dish;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Put;

/**
 * @OA\Tag(
 *     name="Dishes",
 *     description="API Endpoints for Dish Management"
 * )
 */
#[Prefix('auth/dishes')]
class DishController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/auth/dishes",
     *     tags={"Dishes"},
     *     summary="Lấy danh sách món ăn",
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Trang hiện tại",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Số item mỗi trang",
     *         @OA\Schema(type="integer", default=10, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         required=false,
     *         description="Tìm theo tên món (partial match)",
     *         @OA\Schema(type="string", example="Bibimbap")
     *     ),
     *     @OA\Response(response=200, description="Danh sách món ăn")
     * )
     */
    #[Get('/', middleware: ['permission:table-sessions.view'])]
    public function index(Request $request): JsonResponse
    {
        $page  = max((int) $request->get('page', 1), 1);
        $limit = min((int) $request->get('limit', 5), 100);
        $isActive = $request->get('is_active');
        $categoryId = $request->get('category'); // thêm param category_id
        $cookingTime = $request->get('cooking_time');
        $minPrice    = $request->get('min_price');
        $maxPrice    = $request->get('max_price');

        $query = Dish::with('category');

        // Search theo tên
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->get('name') . '%');
        }

        if (!is_null($isActive)) {
            $query->where('is_active', $isActive);
        }

        if (!is_null($categoryId)) {
            $query->where('category_id', $categoryId);
        }

        if (!is_null($cookingTime)) {
            $query->where('cooking_time', $cookingTime);
        }

        // Lọc theo khoảng giá
        if (!is_null($minPrice)) {
            $query->where('price', '>=', $minPrice);
        }
        if (!is_null($maxPrice)) {
            $query->where('price', '<=', $maxPrice);
        }

        $dishes = $query->orderBy('created_at', 'desc')
            ->paginate(perPage: $limit, page: $page);

        return $this->successResponse($dishes, 'Dishes retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/auth/dishes",
     *     tags={"Dishes"},
     *     summary="Tạo mới món ăn",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","price","category_id"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="price", type="number", format="float"),
     *             @OA\Property(property="desc", type="string"),
     *             @OA\Property(property="category_id", type="string"),
     *             @OA\Property(property="cooking_time", type="integer"),
     *             @OA\Property(property="image", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Dish created successfully")
     * )
     */
    #[Post('/', middleware: ['permission:table-sessions.create'])]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'price'        => 'required|numeric|min:0',
            'desc'         => 'nullable|string',
            'category_id'  => 'required|exists:dish_categories,id',
            'cooking_time' => 'nullable|integer|min:0',
            'image'        => 'nullable|string',
        ]);

        $dish = Dish::create($validated);

        return $this->successResponse($dish, 'Dish created successfully', 201);
    }

    /**
     * @OA\Put(
     *     path="/api/auth/dishes/{id}",
     *     tags={"Dishes"},
     *     summary="Cập nhật món ăn",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="price", type="number"),
     *             @OA\Property(property="desc", type="string"),
     *             @OA\Property(property="category_id", type="string"),
     *             @OA\Property(property="cooking_time", type="integer"),
     *             @OA\Property(property="image", type="string"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Dish updated successfully")
     * )
     */
    #[Put('/{id}', middleware: ['permission:table-sessions.edit'])]
    public function update(Request $request, string $id): JsonResponse
    {
        $dish = Dish::findOrFail($id);

        $validated = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'price'        => 'sometimes|numeric|min:0',
            'desc'         => 'nullable|string',
            'category_id'  => 'sometimes|exists:dish_categories,id',
            'cooking_time' => 'nullable|integer|min:0',
            'image'        => 'nullable|string',
            'is_active'    => 'boolean',
        ]);

        $dish->update($validated);

        return $this->successResponse($dish, 'Dish updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/auth/dishes/{id}",
     *     tags={"Dishes"},
     *     summary="Xóa món ăn",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Dish deleted successfully")
     * )
     */
    #[Delete('/{id}', middleware: ['permission:table-sessions.delete'])]
    public function destroy(string $id): JsonResponse
    {
        // Tìm món ăn
        $dish = Dish::findOrFail($id);

        // Kiểm tra xem có OrderItem nào đang chứa dish_id này không
        $hasOrderItems = OrderItem::where('dish_id', $id)->exists();

        if ($hasOrderItems) {
            return $this->errorResponse('Không thể xóa món ăn vì đang được sử dụng trong đơn hàng.', 400);
        }

        // Nếu không có, tiến hành xóa
        $dish->delete();

        return $this->successResponse(null, 'Xóa món ăn thành công');
    }
}
