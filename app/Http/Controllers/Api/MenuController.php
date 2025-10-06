<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
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
    public function index(Request $request): JsonResponse
    {
        $page  = max((int) $request->get('page', 1), 1);
        $limit = min((int) $request->get('limit', 10), 100);
        $isActive = $request->get('is_active');

        $query = Menu::query()->withCount('items');

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->get('name') . '%');
        }

        if (!is_null($isActive)) {
            $query->where('is_active', $isActive);
        }

        $menus = $query->orderBy('created_at', 'desc')
            ->paginate(perPage: $limit, page: $page);

        return $this->successResponse($menus, 'Menus retrieved successfully');
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
                    'Hiện đã có một menu đang được áp dụng. Chỉ được kích hoạt một menu tại một thời điểm.',
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
                    'Chỉ có thể có 1 menu được kích hoạt tại một thời điểm.
                 Menu hiện tại đang hoạt động là: ' . $existingActive->name,
                    422
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

        // Không được xóa nếu menu có item
        $hasItems = MenuItem::where('menu_id', $id)->exists();

        if ($hasItems) {
            return $this->errorResponse('Không thể xóa menu vì có món ăn bên trong.', 400);
        }

        $menu->delete();

        return $this->successResponse(null, 'Xóa menu thành công');
    }
}
