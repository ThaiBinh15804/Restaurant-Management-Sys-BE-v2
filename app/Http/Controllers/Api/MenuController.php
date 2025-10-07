<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\MenuQueryRequest;
use App\Models\Dish;
use App\Models\Menu;
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
#[Prefix('auth/menus')]
class MenuController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/auth/menus",
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
    #[Get('/', middleware: ['permission:table-sessions.view'])]
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

        $paginator = $query->orderBy('created_at', 'desc')
            ->paginate(
                $request->perPage(),
                ['*'],
                'page',
                $request->page()
            );
        $paginator->withQueryString();

        return $this->successResponse([
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ], 'Menus retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/auth/menus",
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
    #[Post('/', middleware: ['permission:table-sessions.create'])]
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
     *     path="/api/auth/menus/{id}",
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
    #[Put('/{id}', middleware: ['permission:table-sessions.edit'])]
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
     *     path="/api/auth/menus/{id}",
     *     tags={"Menus"},
     *     summary="Xóa menu",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Menu deleted successfully")
     * )
     */
    #[Delete('/{id}', middleware: ['permission:table-sessions.delete'])]
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
     *     path="/api/auth/menus/{id}/items",
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
    #[Get('/{id}/items', middleware: ['permission:table-sessions.view'])]
    public function getMenuItems(string $id): JsonResponse
    {
        $menu = Menu::findOrFail($id);

        // Eager load danh sách món ăn
        $items = MenuItem::with('dish') // nếu có quan hệ dish()
            ->where('menu_id', $menu->id)
            ->get()
            ->map(function ($item) {
                return [
                    'id'           => $item->id,
                    'menu_id'      => $item->menu_id,
                    'dish_id'      => $item->dish_id,
                    'dish_name'    => $item->dish->name ?? null,
                    'price'        => $item->price,
                    'notes'        => $item->notes,
                    'dish_image'   => $item->dish->image,
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
     * @OA\Delete(
     *     path="/api/auth/menus/{menuId}/items/{itemId}",
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
    #[Delete('/{menuId}/items/{itemId}', middleware: ['permission:table-sessions.delete'])]
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
     *     path="/api/auth/menus/{menuId}/available-dishes",
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
    #[Get('/{menuId}/available-dishes', middleware: ['permission:table-sessions.view'])]
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
        $query = Dish::select('id', 'name', 'price', 'image');

        if (!empty($existingDishIds)) {
            $query->whereNotIn('id', $existingDishIds);
        }

        $availableDishes = $query->orderBy('name', 'asc')->get();

        return $this->successResponse(
            $availableDishes,
            'Danh sách món ăn chưa có trong menu.'
        );
    }
}
