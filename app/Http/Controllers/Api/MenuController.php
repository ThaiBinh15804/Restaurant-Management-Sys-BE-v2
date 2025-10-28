<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\MenuQueryRequest;
use App\Models\Dish;
use App\Models\Menu;
use App\Models\DishCategory;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Put;

/**
 * @OA\Tag(
 *     name="Menus",
 *     description="API Endpoints for Menu Management"
 * )
 */
#[Prefix('menus')]
class MenuController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/menus",
     *     tags={"Menus"},
     *     summary="Lấy danh sách menu",
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
     *         description="Tìm theo tên menu (partial match)",
     *         @OA\Schema(type="string", example="Thực đơn mùa hè")
     *     ),
     *     @OA\Response(response=200, description="Danh sách menu")
     * )
     */
    #[Get('/', middleware: ['permission:menus.view'])]
    public function index(MenuQueryRequest $request): JsonResponse
    {
        $filters = $request->filters();

        $query = Menu::query()->withCount('items');

        if (!empty($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['desc'])) {
            $query->where('description', 'like', '%' . $filters['desc'] . '%');
        }

        if (!is_null($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        $perPage = $request->perPage();
        $paginator = $query->orderBy('created_at', 'desc')
            ->paginate(
                $perPage
            );

        return $this->successResponse($paginator, 'Menus retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/menus/active/items",
     *     tags={"Menus"},
     *     summary="Lấy danh sách món ăn của menu đang hoạt động (is_active = 1)",
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách món ăn của menu đang được sử dụng",
     *         @OA\JsonContent(
     *             @OA\Property(property="menu", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string")
     *             ),
     *             @OA\Property(property="items", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="menu_id", type="integer"),
     *                     @OA\Property(property="dish_id", type="integer"),
     *                     @OA\Property(property="dish_name", type="string"),
     *                     @OA\Property(property="price_base", type="number", format="float"),
     *                     @OA\Property(property="dish_image", type="string"),
     *                     @OA\Property(property="price", type="number", format="float"),
     *                     @OA\Property(property="notes", type="string")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    #[Get('/active/items', middleware: ['permission:menus.view'])]
    public function getActiveMenuItems(): JsonResponse
    {
        // Lấy menu đang hoạt động
        $menu = Menu::where('is_active', 1)->first();

        // Nếu không có menu nào active thì trả về lỗi nhẹ
        if (!$menu) {
            return $this->errorResponse('Không có menu nào đang hoạt động.', 404);
        }

        // Lấy danh sách món trong menu đang hoạt động
        $items = MenuItem::with('dish')
            ->where('menu_id', $menu->id)
            ->get()
            ->map(function ($item) {
                return [
                    'id'           => $item->id,
                    'menu_id'      => $item->menu_id,
                    'dish_id'      => $item->dish_id,
                    'dish_name'    => $item->dish->name ?? null,
                    'price_base'   => $item->dish->price ?? 0,
                    'dish_image'   => $item->dish->image,
                    'price'        => $item->price,
                    'notes'        => $item->notes,
                ];
            });

        return $this->successResponse([
            'menu' => [
                'id'   => $menu->id,
                'name' => $menu->name,
            ],
            'items' => $items,
        ], 'The list of dishes in the active menu was retrieved successfully.');
    }

    /**
     * @OA\Post(
     *     path="/api/menus",
     *     tags={"Menus"},
     *     summary="Tạo mới menu",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "version"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="version", type="integer", example=1),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Menu created successfully")
     * )
     */
    #[Post('/', middleware: ['permission:menus.create'])]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'version'     => 'required|integer|min:1',
            'is_active'   => 'boolean',
        ]);

        // Nếu client gửi is_active = true => kiểm tra xem có menu nào đang active chưa
        if (!empty($validated['is_active']) && $validated['is_active'] === true) {
            $activeMenuExists = Menu::where('is_active', true)->exists();

            if ($activeMenuExists) {
                return $this->errorResponse(
                    'There is currently one menu in use. Only one menu can be active at a time.',
                    400
                );
            }
        }

        $menu = Menu::create($validated);

        return $this->successResponse($menu, 'Menu created successfully', 201);
    }

    /**
     * @OA\Put(
     *     path="/api/menus/{id}",
     *     tags={"Menus"},
     *     summary="Cập nhật menu",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="version", type="integer"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Menu updated successfully")
     * )
     */
    #[Put('/{id}', middleware: ['permission:menus.edit'])]
    public function update(Request $request, string $id): JsonResponse
    {
        $menu = Menu::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'version'     => 'sometimes|integer|min:1',
            'is_active'   => 'boolean',
        ]);

        // ✅ Nếu người dùng gửi is_active = true
        if (array_key_exists('is_active', $validated) && $validated['is_active'] === true) {
            $existingActive = Menu::where('is_active', true)
                ->where('id', '!=', $menu->id)
                ->first();

            if ($existingActive) {
                return $this->errorResponse(
                    'Only 1 menu can be active at a time. The currently active menu is: ' . $existingActive->name,
                    400
                );
            }
        }

        $menu->update($validated);

        return $this->successResponse($menu, 'Menu updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/menus/{id}",
     *     tags={"Menus"},
     *     summary="Xóa menu",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Menu deleted successfully")
     * )
     */
    #[Delete('/{id}', middleware: ['permission:menus.delete'])]
    public function destroy(string $id): JsonResponse
    {
        $menu = Menu::findOrFail($id);

        // Không được xóa nếu menu đang hoạt động
        if ($menu->is_active) {
            return $this->errorResponse('Cannot delete active menu.', 400);
        }

        // Nếu menu không hoạt động, tiến hành xóa các item liên quan
        MenuItem::where('menu_id', $menu->id)->delete();

        // Sau đó xóa menu
        $menu->delete();

        return $this->successResponse(null, 'Delete menu and related dishes successfully.');
    }

    /**
     * @OA\Get(
     *     path="/api/menus/{id}/items",
     *     tags={"Menus"},
     *     summary="Lấy danh sách món ăn trong menu",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID của menu",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách món ăn thuộc menu",
     *         @OA\JsonContent(
     *             @OA\Property(property="items", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="menu_id", type="integer"),
     *                     @OA\Property(property="dish_id", type="integer"),
     *                     @OA\Property(property="dish_name", type="string"),
     *                     @OA\Property(property="price", type="number", format="float"),
     *                     @OA\Property(property="is_available", type="boolean")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    #[Get('/{id}/items', middleware: ['permission:menus.view'])]
    public function getMenuItems(string $id): JsonResponse
    {
        $menu = Menu::findOrFail($id);

        $items = MenuItem::with('dish') // nếu có quan hệ dish()
            ->where('menu_id', $menu->id)
            ->get()
            ->map(function ($item) {
                return [
                    'id'           => $item->id,
                    'menu_id'      => $item->menu_id,
                    'dish_id'      => $item->dish_id,
                    'dish_name'    => $item->dish->name ?? null,
                    'price_base'   => $item->dish->price ?? 0,
                    'dish_image'   => $item->dish->image,
                    'price'        => $item->price,
                    'notes'        => $item->notes,
                ];
            });

        return $this->successResponse([
            'menu'  => [
                'id'   => $menu->id,
                'name' => $menu->name,
            ],
            'items' => $items,
        ], 'The list of dishes in the menu was successfully retrieved.');
    }

    /**
     * @OA\Post(
     *     path="/api/menus/{menuId}/items",
     *     tags={"Menus"},
     *     summary="Thêm món ăn vào menu",
     *     description="Tạo mới liên kết giữa menu và món ăn, không cho phép trùng món trong cùng menu",
     *     @OA\Parameter(
     *         name="menuId",
     *         in="path",
     *         required=true,
     *         description="ID của menu",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"dish_id", "price"},
     *             @OA\Property(property="dish_id", type="string", example="D001"),
     *             @OA\Property(property="price", type="number", format="float", example=45000),
     *             @OA\Property(property="notes", type="string", example="Món thêm topping trứng"),
     *         )
     *     ),
     *     @OA\Response(response=201, description="Món ăn được thêm vào menu thành công"),
     *     @OA\Response(response=400, description="Món đã tồn tại trong menu hoặc dữ liệu không hợp lệ"),
     *     @OA\Response(response=404, description="Không tìm thấy menu hoặc món ăn")
     * )
     */
    #[Post('/{menuId}/items', middleware: ['permission:menus.create'])]
    public function addMenuItem(Request $request, string $menuId): JsonResponse
    {
        $menu = Menu::find($menuId);

        if (!$menu) {
            return $this->errorResponse('Menu not found.', 404);
        }

        $validated = $request->validate([
            'dish_id' => 'required|string|exists:dishes,id',
            'price'   => 'required|numeric|min:0',
            'notes'   => 'nullable|string|max:255',
        ]);

        // Kiểm tra món đã tồn tại trong menu chưa
        $exists = MenuItem::where('menu_id', $menuId)
            ->where('dish_id', $validated['dish_id'])
            ->exists();

        if ($exists) {
            return $this->errorResponse('This dish already exists in the menu.', 400);
        }

        // Tạo mới menu item
        $menuItem = MenuItem::create([
            'menu_id' => $menuId,
            'dish_id' => $validated['dish_id'],
            'price'   => $validated['price'],
            'notes'   => $validated['notes'] ?? null,
        ]);

        // Lấy lại kèm thông tin món
        $menuItem->load('dish');

        return $this->successResponse(
            [
                'id'         => $menuItem->id,
                'dish_id'    => $menuItem->dish_id,
                'dish_name'  => $menuItem->dish->name ?? null,
                'price'      => $menuItem->price,
                'notes'      => $menuItem->notes,
                'dish_image' => $menuItem->dish->image ?? null,
            ],
            'Dish added to menu successfully.',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/menus/{menuId}/items/{itemId}",
     *     summary="Cập nhật món ăn trong menu",
     *     description="Cập nhật thông tin món ăn (price, notes, dish_id) trong menu.",
     *     operationId="updateMenuItem",
     *     tags={"Menu Items"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="menuId",
     *         in="path",
     *         required=true,
     *         description="ID của menu chứa món ăn",
     *         @OA\Schema(type="string", example="MN001")
     *     ),
     *     @OA\Parameter(
     *         name="itemId",
     *         in="path",
     *         required=true,
     *         description="ID của menu item cần cập nhật",
     *         @OA\Schema(type="string", example="MI001")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"dish_id", "price"},
     *             @OA\Property(property="dish_id", type="string", example="D001", description="ID món ăn mới"),
     *             @OA\Property(property="price", type="number", format="float", example=45000, description="Giá áp dụng trong menu"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Món đặc biệt trong tuần", description="Ghi chú thêm"),
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Menu item updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Menu item updated successfully."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", example="MI001"),
     *                 @OA\Property(property="menu_id", type="string", example="MN001"),
     *                 @OA\Property(property="dish_id", type="string", example="D001"),
     *                 @OA\Property(property="dish_name", type="string", example="Cơm chiên hải sản"),
     *                 @OA\Property(property="price", type="number", example=45000),
     *                 @OA\Property(property="price_base", type="number", example=40000),
     *                 @OA\Property(property="notes", type="string", example="Món đặc biệt trong tuần"),
     *                 @OA\Property(property="dish_image", type="string", example="/uploads/dishes/comchien.jpg")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="This dish already exists in the menu."
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Menu or Menu Item not found."
     *     ),
     * )
     */
    #[Put('/{menuId}/items/{itemId}', middleware: ['permission:menus.edit'])]
    public function updateMenuItem(Request $request, string $menuId, string $itemId): JsonResponse
    {
        // Kiểm tra menu tồn tại
        $menu = Menu::find($menuId);
        if (!$menu) {
            return $this->errorResponse('Menu not found.', 404);
        }

        // Kiểm tra item có thuộc menu đó không
        $menuItem = MenuItem::where('menu_id', $menuId)
            ->where('id', $itemId)
            ->first();

        if (!$menuItem) {
            return $this->errorResponse('Menu item not found.', 404);
        }

        // Validate dữ liệu
        $validated = $request->validate([
            'dish_id' => 'required|string|exists:dishes,id',
            'price'   => 'required|numeric|min:0',
            'notes'   => 'nullable|string|max:255',
        ]);

        // Kiểm tra nếu dish_id thay đổi và trùng với món khác trong cùng menu
        $exists = MenuItem::where('menu_id', $menuId)
            ->where('dish_id', $validated['dish_id'])
            ->where('id', '!=', $itemId)
            ->exists();

        if ($exists) {
            return $this->errorResponse('This dish already exists in the menu.', 400);
        }

        // Cập nhật dữ liệu
        $menuItem->update([
            'dish_id' => $validated['dish_id'],
            'price'   => $validated['price'],
            'notes'   => $validated['notes'] ?? null,
            'updated_at' => now(),
        ]);

        // Load lại thông tin món ăn
        $menuItem->load('dish');

        return $this->successResponse(
            [
                'id'          => $menuItem->id,
                'menu_id'     => $menuItem->menu_id,
                'dish_id'     => $menuItem->dish_id,
                'dish_name'   => $menuItem->dish->name ?? null,
                'price'       => $menuItem->price,
                'notes'       => $menuItem->notes,
                'dish_image'  => $menuItem->dish->image ?? null,
                'price_base'  => $menuItem->dish->price ?? null, // thêm base price từ bảng dish
            ],
            'Menu item updated successfully.'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/menus/{menuId}/items/{itemId}",
     *     tags={"Menus"},
     *     summary="Xóa một món ăn khỏi menu",
     *     description="Xóa liên kết giữa món ăn và menu, không xóa món trong cơ sở dữ liệu món ăn",
     *     @OA\Parameter(
     *         name="menuId",
     *         in="path",
     *         required=true,
     *         description="ID của menu",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="itemId",
     *         in="path",
     *         required=true,
     *         description="ID của món ăn trong menu (menu_item_id)",
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Xóa món ăn khỏi menu thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Món ăn đã được xóa khỏi menu.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Không tìm thấy menu hoặc món trong menu"
     *     )
     * )
     */
    #[Delete('/{menuId}/items/{itemId}', middleware: ['permission:menus.delete'])]
    public function deleteMenuItem(string $menuId, string $itemId): JsonResponse
    {
        $menu = Menu::find($menuId);

        if (!$menu) {
            return $this->errorResponse('Menu not found.', 404);
        }

        $menuItem = MenuItem::where('menu_id', $menuId)
            ->where('id', $itemId)
            ->first();

        if (!$menuItem) {
            return $this->errorResponse('The dish was not found in the menu..', 404);
        }

        $menuItem->delete();

        return $this->successResponse(null, 'The dish has been removed from the menu.');
    }

    /**
     * @OA\Get(
     *     path="/api/menus/{menuId}/available-dishes",
     *     tags={"Menus"},
     *     summary="Lấy danh sách món ăn chưa có trong menu",
     *     description="Trả về danh sách các món ăn chưa xuất hiện trong menu để thêm mới",
     *     @OA\Parameter(
     *         name="menuId",
     *         in="path",
     *         required=true,
     *         description="ID của menu",
     *         @OA\Schema(type="string", example="MN001")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách món ăn chưa có trong menu",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Danh sách món ăn chưa có trong menu."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="string", example="D001"),
     *                     @OA\Property(property="name", type="string", example="Cơm chiên hải sản"),
     *                     @OA\Property(property="price", type="number", format="float", example=45000),
     *                     @OA\Property(property="image", type="string", example="/uploads/dishes/com-chien.jpg")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Không tìm thấy menu"),
     *     @OA\Response(response=500, description="Lỗi server")
     * )
     */
    #[Get('/{menuId}/available-dishes', middleware: ['permission:menus.view'])]
    public function getAvailableDishes(string $menuId): JsonResponse
    {
        $menu = Menu::find($menuId);

        if (!$menu) {
            return $this->errorResponse('Menu not found.', 404);
        }

        $existingDishIds = MenuItem::where('menu_id', $menuId)
            ->pluck('dish_id')
            ->toArray();

        // Lấy danh sách món ăn chưa có trong menu
        $query = Dish::select('id', 'name', 'price', 'image')->where("is_active", 1);

        if (!empty($existingDishIds)) {
            $query->whereNotIn('id', $existingDishIds);
        }

        $availableDishes = $query->orderBy('name', 'asc')->get();

        return $this->successResponse(
            $availableDishes,
            'Danh sách món ăn chưa có trong menu.'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/active/categories",
     *     tags={"Statistics"},
     *     summary="Get active menu categories with dishes",
     *     description="Retrieve dish categories with up to 4 active dishes from the current menu",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="menu_id",
     *         in="query",
     *         description="Filter by specific menu ID (optional)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit_dishes",
     *         in="query",
     *         description="Limit number of dishes per category (default: 4)",
     *         required=false,
     *         @OA\Schema(type="integer", example=4)
     *     ),
     *     @OA\Parameter(
     *         name="category_name",
     *         in="query",
     *         description="Filter categories by name (optional, supports partial match)",
     *         required=false,
     *         @OA\Schema(type="string", example="Món chính")
     *     ),
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status of category (true/false)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Response(response=200, description="Categories with dishes retrieved successfully"),
     *     @OA\Response(response=404, description="No menu found")
     * )
     */
    #[Get('/active/categories')]
    public function getActiveMenuCategoriesWithDishes(Request $request): JsonResponse
    {
        $menu = Menu::where('is_active', 1)->first();
        if (!$menu) {
            return $this->errorResponse('Không có menu nào đang hoạt động.', 404);
        }
        $dishIds = MenuItem::where('menu_id', $menu->id)->pluck('dish_id');
        $categories = DishCategory::whereHas('dishes', function ($q) use ($dishIds) {
            $q->whereIn('id', $dishIds)->where('is_active', true);
        })
            ->with(['dishes' => function ($q) use ($dishIds) {
                $q->whereIn('id', $dishIds)
                    ->take(4);
            }])
            ->get();

        return $this->successResponse($categories, 'Danh mục và món ăn trong menu active');
    }

    /**
     * @OA\Get(
     *     path="/api/auth/menus/filter-dishes",
     *     tags={"Menus"},
     *     summary="Lọc món ăn theo giá, danh mục, trạng thái và từ khóa",
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Từ khóa (tên món, mô tả, tên danh mục)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort_price",
     *         in="query",
     *         description="asc: giá thấp đến cao, desc: giá cao đến thấp",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="ID danh mục món ăn",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Trạng thái món ăn (true/false hoặc 1/0)",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(response=200, description="Danh sách món ăn đã lọc")
     * )
     */
    #[Get('/filter-dishes')]
    public function filterDishes(Request $request): JsonResponse
    {
        $query = Dish::query()->with('category');

        // Tìm theo từ khóa: luôn dùng starts-with, case-insensitive
        if ($request->filled('q')) {
            $kw = trim((string) $request->q);
            if ($kw !== '') {
                $prefix = mb_strtolower($kw, 'UTF-8') . '%';
                $query->where(function ($sub) use ($prefix) {
                    $sub->whereRaw('LOWER(name) LIKE ?', [$prefix])
                        ->orWhereRaw('LOWER(`desc`) LIKE ?', [$prefix])
                        ->orWhereHas('category', function ($cat) use ($prefix) {
                            $cat->whereRaw('LOWER(name) LIKE ?', [$prefix]);
                        });
                });
            }
        }

        // Lọc theo danh mục
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Lọc theo trạng thái
        if (!is_null($request->is_active)) {
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        // Sắp xếp theo giá
        if ($request->sort_price === 'asc') {
            $query->orderBy('price', 'asc');
        } elseif ($request->sort_price === 'desc') {
            $query->orderBy('price', 'desc');
        }

        $dishes = $query->get();

        return $this->successResponse($dishes, 'Danh sách món ăn đã lọc');
    }

    /**
     * @OA\Get(
     *     path="/api/auth/menus/with-items",
     *     tags={"Menus"},
     *     summary="Lấy tất cả menu kèm các món (menu items)",
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         required=false,
     *         description="Lọc menu theo trạng thái (true/false)",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="limit_items",
     *         in="query",
     *         required=false,
     *         description="Giới hạn số menu_item mỗi menu (vd: 5)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="only_active_dishes",
     *         in="query",
     *         required=false,
     *         description="Chỉ lấy menu items có dish đang active (true/false)",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(response=200, description="Danh sách menu kèm items")
     * )
     */
    #[Get('/with-items')]
    public function listMenusWithItems(Request $request): JsonResponse
    {
        $isActive          = $request->has('is_active') ? filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
        $limitItems        = $request->integer('limit_items') ?: null;
        $onlyActiveDishes  = $request->has('only_active_dishes')
            ? filter_var($request->query('only_active_dishes'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : false;

        $menusQuery = Menu::query()->with(['items.dish']);

        if (!is_null($isActive)) {
            $menusQuery->where('is_active', $isActive);
        }

        $menus = $menusQuery
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($menu) use ($limitItems, $onlyActiveDishes) {
                $items = $menu->items
                    ->filter(function ($item) use ($onlyActiveDishes) {
                        if ($onlyActiveDishes) {
                            return optional($item->dish)->is_active === 1;
                        }
                        return true;
                    })
                    ->values();

                if ($limitItems && $limitItems > 0) {
                    $items = $items->take($limitItems);
                }

                return [
                    'id'          => $menu->id,
                    'name'        => $menu->name,
                    'description' => $menu->description,
                    'version'     => $menu->version,
                    'is_active'   => (bool)$menu->is_active,
                    'items'       => $items->map(function ($it) {
                        return [
                            'id'          => $it->id,
                            'dish_id'     => $it->dish_id,
                            'dish_name'   => $it->dish->name ?? null,
                            'dish_image'  => $it->dish->image ?? null,
                            'price_base'  => $it->dish->price ?? null,
                            'price'       => $it->price,
                            'desc'   => $it->dish->desc ?? null,
                            'notes'       => $it->notes,
                            'dish_active' => optional($it->dish)->is_active ? true : false,
                        ];
                    }),
                ];
            });

        return $this->successResponse($menus, 'Menus with items retrieved successfully');
    }
}
