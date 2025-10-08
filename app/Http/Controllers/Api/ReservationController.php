<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reservation\ReservationQueryRequest;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;

/**
 * @OA\Tag(
 *     name="Reservation",
 *     description="API Endpoints for Reservation Management"
 * )
 */
#[Prefix('auth/reservations')]
class ReservationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/auth/reservations",
     *     tags={"Reservations"},
     *     summary="Lấy danh sách đặt bàn",
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Trang hiện tại",
     *         @OA\Schema(
     *             type="integer",
     *             default=1
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Số item mỗi trang",
     *         @OA\Schema(
     *             type="integer",
     *             default=10,
     *             maximum=100
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Trạng thái đặt bàn: 0=Pending, 1=Confirmed, 2=Cancelled, 3=Completed",
     *         @OA\Schema(
     *             type="integer",
     *             enum={0,1,2,3}
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="customer_name",
     *         in="query",
     *         required=false,
     *         description="Tìm theo tên khách hàng (partial match)",
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách reservations với thông tin customer"
     *     )
     * )
     */
    #[Get('/', middleware: ['permission:reservations.view'])]
    public function index(ReservationQueryRequest $request): JsonResponse
    {
        $filters = $request->filters(); // lấy filter từ request

        $query = Reservation::with('customer');

        // Lọc theo status nếu có
        if (!is_null($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $paginator = $query->orderBy('reserved_at', 'desc')
            ->paginate($request->perPage(), ['*'], 'page', $request->page());
        $paginator->withQueryString();

        return $this->successResponse([
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ], 'Reservations retrieved successfully');
    }
}
