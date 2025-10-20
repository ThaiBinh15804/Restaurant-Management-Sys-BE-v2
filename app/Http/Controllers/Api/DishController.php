<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dish\DishQueryRequest;
use App\Models\Dish;
use App\Models\DishIngredient;
use App\Models\Ingredient;
use App\Models\OrderItem;
use App\Traits\HasFileUpload;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Put;

/**
 * @OA\Tag(
 *     name="Dishes",
 *     description="API Endpoints for Dish Management"
 * )
 */
#[Prefix('dishes')]
class DishController extends Controller
{
    use HasFileUpload;

    /**
     * @OA\Get(
     *     path="/api/dishes",
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
    #[Get('/', middleware: ['permission:dishes.view'])]
    public function index(DishQueryRequest $request): JsonResponse
    {
        $query = Dish::with('category')
            ->orderBy('created_at', 'desc');

        $filters = $request->filters();

        if (!empty($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (!is_null($filters['is_active'] ?? null)) {
            $query->where('is_active', $filters['is_active']);
        }

        if (!is_null($filters['category'] ?? null)) {
            $query->where('category_id', $filters['category']);
        }

        if (!is_null($filters['cooking_time'] ?? null)) {
            $query->where('cooking_time', $filters['cooking_time']);
        }

        if (!is_null($filters['min_price'] ?? null)) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (!is_null($filters['max_price'] ?? null)) {
            $query->where('price', '<=', $filters['max_price']);
        }

        $perPage = $request->perPage();
        $paginator = $query->paginate(
            $perPage
        );

        return $this->successResponse($paginator, 'Dishes retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/dishes",
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
    #[Post('/', middleware: ['permission:dishes.create'])]
    public function store(Request $request): JsonResponse
    {
        $request->merge([
            'is_active' => filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN),
        ]);

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'price'        => 'required|numeric|min:0',
            'desc'         => 'required|string',
            'category_id'  => 'required|exists:dish_categories,id',
            'cooking_time' => 'required|integer|min:0',
            'is_active'    => 'required|boolean',
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:2048'],
        ]);

        $dish = Dish::create($validated);

        if ($request->hasFile('image')) {
            $entityType = $this->getEntityTypeFromController(); // => "dish"
            $oldImage = $dish->image;
            $imageUrl = $this->uploadFile(
                $request->file('image'),
                $entityType,      // tự động lấy từ tên controller
                $dish->id,
                $oldImage
            );

            $dish->update(['image' => $imageUrl]);
        }

        return $this->successResponse($dish, 'Dish created successfully', 201);
    }

    /**
     * @OA\Put(
     *     path="/api/dishes/{id}",
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
    #[Put('/{id}', middleware: ['permission:dishes.edit'])]
    public function update(Request $request, string $id): JsonResponse
    {
        $request->merge([
            'is_active' => filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN),
        ]);

        $dish = Dish::findOrFail($id);

        $validated = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'price'        => 'sometimes|numeric|min:0',
            'desc'         => 'nullable|string',
            'category_id'  => 'sometimes|exists:dish_categories,id',
            'cooking_time' => 'nullable|integer|min:0',
            'is_active'    => 'boolean',
            'image'        => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:2048'],
        ]);

        if ($request->has('image')) {
            Log::info('has image field', ['type' => gettype($request->input('image'))]);
        }

        if ($request->hasFile('image')) {
            Log::info('✅ hasFile true');
        } else {
            Log::warning('❌ hasFile false');
        }

        // 🟢 Upload ảnh trước khi update DB
        if ($request->hasFile('image')) {
            $entityType = $this->getEntityTypeFromController();
            $oldImage = $dish->image;

            $imageUrl = $this->uploadFile(
                $request->file('image'),
                $entityType,
                $dish->id,
                $oldImage
            );

            $validated['image'] = $imageUrl; // thêm image mới vào payload
        }

        $dish->update($validated);

        return $this->successResponse($dish->fresh(), 'Dish updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/dishes/{id}",
     *     tags={"Dishes"},
     *     summary="Xóa món ăn",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Dish deleted successfully")
     * )
     */
    #[Delete('/{id}', middleware: ['permission:dishes.delete'])]
    public function destroy(string $id): JsonResponse
    {
        // Tìm món ăn
        $dish = Dish::findOrFail($id);

        // Kiểm tra xem có OrderItem nào đang chứa dish_id này không
        $hasOrderItems = OrderItem::where('dish_id', $id)->exists();

        if ($hasOrderItems) {
            return $this->errorResponse('The dish cannot be deleted because it is being used in the menu.', 400);
        }

        // Nếu không có, tiến hành xóa
        $dish->delete();

        return $this->successResponse(null, 'Deleted dish successfully.');
    }

    /**
     * @OA\Get(
     *     path="/api/auth/dishes/popular",
     *     tags={"Dishes"},
     *     summary="Lấy danh sách món ăn phổ biến nhất",
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Số lượng món ăn trả về",
     *         @OA\Schema(type="integer", default=5)
     *     ),
     *     @OA\Response(response=200, description="Danh sách món ăn phổ biến")
     * )
     */
    #[Get('/popular')]
    public function popular(Request $request): JsonResponse
    {
        $limit = $request->query('limit', 5);

        $popularDishes = Dish::with('category')
            ->select('dishes.*')
            ->leftJoin('order_items', 'dishes.id', '=', 'order_items.dish_id')
            ->selectRaw('COUNT(order_items.id) as order_count')
            ->groupBy('dishes.id')
            ->orderByDesc('order_count')
            ->limit($limit)
            ->get();

        return $this->successResponse($popularDishes, 'Popular dishes retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/auth/dishes/{id}",
     *     tags={"Dishes"},
     *     summary="Lấy thông tin chi tiết món ăn theo ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID của món ăn",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Chi tiết món ăn"),
     *     @OA\Response(response=404, description="Không tìm thấy món ăn")
     * )
     */
    #[Get('/{id}')]
    public function show(string $id): JsonResponse
    {
        $dish = Dish::with('category')->findOrFail($id);
        return $this->successResponse($dish, 'Dish retrieved successfully');
    }

    /**
     * Lấy danh sách nguyên liệu thuộc về một món ăn cụ thể
     */
    /**
     * @OA\Get(
     *     path="/api/auth/dishes/{id}/ingredients",
     *     tags={"Dishes"},
     *     summary="Lấy danh sách nguyên liệu của một món ăn",
     *     description="Trả về danh sách nguyên liệu, định lượng và ghi chú cho món ăn theo ID.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID của món ăn cần lấy nguyên liệu",
     *         @OA\Schema(type="string", example="DISH0001")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lấy danh sách nguyên liệu thành công",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Lấy danh sách nguyên liệu thành công"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="string", example="ING0001"),
     *                     @OA\Property(property="name", type="string", example="Thịt bò"),
     *                     @OA\Property(property="unit", type="string", example="gram"),
     *                     @OA\Property(property="quantity", type="number", format="float", example=200),
     *                     @OA\Property(property="note", type="string", nullable=true, example="Thịt nạc vai")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Không tìm thấy món ăn",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Không tìm thấy món ăn")
     *         )
     *     )
     * )
     */
    #[Get('/{id}/ingredients', middleware: ['permission:dishes.view'])]
    public function getIngredients(string $id): JsonResponse
    {
        // Lấy toàn bộ bản ghi từ bảng dish_ingredient theo dish_id
        $ingredients = DishIngredient::where('dish_id', $id)
            ->join('ingredients', 'dish_ingredient.ingredient_id', '=', 'ingredients.id')
            ->select(
                'dish_ingredient.id',
                'dish_ingredient.dish_id',
                'dish_ingredient.ingredient_id',
                'ingredients.name',
                'ingredients.unit',
                'ingredients.current_stock',
                'dish_ingredient.quantity',
                'dish_ingredient.note',
                'dish_ingredient.created_at',
                'dish_ingredient.updated_at'
            )
            ->get();

        if ($ingredients->isEmpty()) {
            return $this->errorResponse('Không tìm thấy nguyên liệu cho món ăn này', [], 404);
        }

        return $this->successResponse($ingredients, 'Lấy danh sách nguyên liệu thành công');
    }

    /**
     * @OA\Get(
     *     path="/api/auth/dishes/{id}/available-ingredients",
     *     tags={"Dishes"},
     *     summary="Lấy danh sách nguyên liệu chưa được gắn với món ăn",
     *     description="Trả về danh sách tất cả nguyên liệu chưa liên kết với món ăn cụ thể.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID của món ăn",
     *         @OA\Schema(type="string", example="DISH0001")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lấy danh sách nguyên liệu chưa gắn thành công",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Lấy danh sách nguyên liệu chưa gắn thành công"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="string", example="ING0005"),
     *                     @OA\Property(property="name", type="string", example="Hành lá"),
     *                     @OA\Property(property="unit", type="string", example="gram"),
     *                     @OA\Property(property="current_stock", type="number", format="float", example=50)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Không tìm thấy món ăn",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Không tìm thấy món ăn")
     *         )
     *     )
     * )
     */
    #[Get('/{id}/available-ingredients', middleware: ['permission:dishes.view'])]
    public function getAvailableIngredients(string $id): JsonResponse
    {
        // Kiểm tra món ăn tồn tại
        $dish = Dish::find($id);
        if (!$dish) {
            return $this->errorResponse('Không tìm thấy món ăn', [], 404);
        }

        // Lấy danh sách ingredient_id đã gắn với món ăn
        $linkedIngredientIds = DishIngredient::where('dish_id', $id)
            ->pluck('ingredient_id')
            ->toArray();

        // Lấy danh sách nguyên liệu chưa được gắn
        $availableIngredients = Ingredient::query()
            ->whereNotIn('id', $linkedIngredientIds)
            ->where('is_active', true)
            ->select('id', 'name', 'unit', 'current_stock')
            ->orderBy('name')
            ->get();

        return $this->successResponse($availableIngredients, 'Lấy danh sách nguyên liệu chưa gắn thành công',);
    }

    /**
     * @OA\Post(
     *     path="/api/dishes/{dishId}/ingredients",
     *     tags={"Dishes"},
     *     summary="Thêm nguyên liệu vào món ăn",
     *     description="Tạo liên kết mới giữa món ăn và nguyên liệu.",
     *     @OA\Parameter(
     *         name="dishId",
     *         in="path",
     *         required=true,
     *         description="ID của món ăn",
     *         @OA\Schema(type="string", example="DISH0001")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"ingredient_id","quantity"},
     *             @OA\Property(property="ingredient_id", type="string", example="ING0005", description="ID của nguyên liệu muốn thêm"),
     *             @OA\Property(property="quantity", type="string", example="50", description="Số lượng nguyên liệu"),
     *             @OA\Property(property="notes", type="string", example="Dùng cho món canh", description="Ghi chú (tùy chọn)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Thêm nguyên liệu thành công",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Ingredient added to dish successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="dish_id", type="string", example="DISH0001"),
     *                 @OA\Property(property="ingredient_id", type="string", example="ING0005"),
     *                 @OA\Property(property="ingredient_name", type="string", example="Hành lá"),
     *                 @OA\Property(property="quantity", type="string", example="50"),
     *                 @OA\Property(property="notes", type="string", example="Dùng cho món canh")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Nguyên liệu đã được gắn cho món ăn",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This ingredient is already assigned to the dish.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Không tìm thấy món ăn",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Dish not found.")
     *         )
     *     )
     * )
     */
    #[Post('/{dishId}/ingredients', middleware: ['permission:dishes.edit'])]
    public function addIngredientToDish(Request $request, string $dishId): JsonResponse
    {
        // Kiểm tra món ăn tồn tại
        $dish = Dish::find($dishId);
        if (!$dish) {
            return $this->errorResponse('Dish not found.', 404);
        }

        // Validate dữ liệu gửi lên
        $validated = $request->validate([
            'ingredient_id' => 'required|string|exists:ingredients,id',
            'quantity'      => 'required|string|max:255',
            'notes'         => 'nullable|string|max:255',
        ]);

        // Kiểm tra nguyên liệu đã được gắn cho món chưa (tránh duplicate)
        $exists = DishIngredient::where('dish_id', $dishId)
            ->where('ingredient_id', $validated['ingredient_id'])
            ->exists();

        if ($exists) {
            return $this->errorResponse('This ingredient is already assigned to the dish.', 400);
        }

        // Tạo bản ghi mới
        $dishIngredient = DishIngredient::create([
            'dish_id'       => $dishId,
            'ingredient_id' => $validated['ingredient_id'],
            'quantity'      => $validated['quantity'],
            'note'         => $validated['notes'] ?? null,
        ]);

        // Load quan hệ ingredient
        $dishIngredient->load('ingredient');

        return $this->successResponse([
            'dish_id'         => $dishIngredient->dish_id,
            'ingredient_id'   => $dishIngredient->ingredient_id,
            'ingredient_name' => $dishIngredient->ingredient->name ?? null,
            'quantity'        => $dishIngredient->quantity,
            'notes'           => $dishIngredient->notes,
        ], 'Ingredient added to dish successfully.');
    }


    /**
     * @OA\Put(
     *     path="/api/dishes/{dishId}/ingredients/{ingredientId}",
     *     tags={"Dishes"},
     *     summary="Cập nhật nguyên liệu thuộc món ăn",
     *     description="Cập nhật quantity và notes của một nguyên liệu thuộc một món ăn",
     *     @OA\Parameter(
     *         name="dishId",
     *         in="path",
     *         required=true,
     *         description="ID của món ăn",
     *         @OA\Schema(type="string", example="DISH0001")
     *     ),
     *     @OA\Parameter(
     *         name="ingredientId",
     *         in="path",
     *         required=true,
     *         description="ID của nguyên liệu",
     *         @OA\Schema(type="string", example="ING0005")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"quantity"},
     *             @OA\Property(property="quantity", type="string", description="Số lượng sử dụng của nguyên liệu", example="0.5 kg"),
     *             @OA\Property(property="notes", type="string", description="Ghi chú về nguyên liệu", example="Nguyên liệu tươi")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Nguyên liệu cập nhật thành công",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Ingredient updated successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="dish_id", type="string", example="DISH0001"),
     *                 @OA\Property(property="ingredient_id", type="string", example="ING0005"),
     *                 @OA\Property(property="ingredient_name", type="string", example="Hành lá"),
     *                 @OA\Property(property="quantity", type="string", example="0.5 kg"),
     *                 @OA\Property(property="notes", type="string", example="Nguyên liệu tươi")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Món ăn hoặc nguyên liệu không tồn tại",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Dish not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Dữ liệu gửi lên không hợp lệ",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={"quantity": {"The quantity field is required."}}
     *             )
     *         )
     *     )
     * )
     */
    #[Put('/{dishId}/ingredients/{idIngredientDish}', middleware: ['permission:dishes.edit'])]
    public function updateIngredientDish(Request $request, string $dishId, string $idIngredientDish): JsonResponse
    {
        // Kiểm tra món ăn tồn tại
        $dish = Dish::find($dishId);
        if (!$dish) {
            return $this->errorResponse('Dish not found.', 404);
        }

        // Validate dữ liệu gửi lên
        $validated = $request->validate([
            'ingredient_id' => 'required|string|exists:ingredients,id', // nguyên liệu mới
            'quantity'      => 'required|string|max:255',
            'notes'         => 'nullable|string|max:255',
        ]);

        // Lấy bản ghi dish_ingredient theo id
        $dishIngredient = DishIngredient::where('dish_id', $dishId)
            ->where('id', $idIngredientDish)
            ->first();

        if (!$dishIngredient) {
            return $this->errorResponse('Ingredient record not found for this dish.', 404);
        }

        // Kiểm tra nguyên liệu mới đã gắn cho món chưa (tránh duplicate)
        $exists = DishIngredient::where('dish_id', $dishId)
            ->where('ingredient_id', $validated['ingredient_id'])
            ->where('id', '!=', $dishIngredient->id)
            ->exists();

        if ($exists) {
            return $this->errorResponse('This ingredient is already assigned to the dish.', 400);
        }

        // Ghi đè nguyên liệu mới
        $dishIngredient->update([
            'ingredient_id' => $validated['ingredient_id'],
            'quantity'      => $validated['quantity'],
            'note'         => $validated['notes'] ?? null,
            'updated_at'    => now(),
        ]);

        // Load quan hệ ingredient
        $dishIngredient->load('ingredient');

        return $this->successResponse([
            'dish_id'         => $dishIngredient->dish_id,
            'ingredient_id'   => $dishIngredient->ingredient_id,
            'ingredient_name' => $dishIngredient->ingredient->name ?? null,
            'quantity'        => $dishIngredient->quantity,
            'notes'           => $dishIngredient->notes,
        ], 'Ingredient updated successfully.');
    }
}
