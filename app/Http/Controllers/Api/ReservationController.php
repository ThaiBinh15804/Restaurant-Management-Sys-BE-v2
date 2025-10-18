<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reservation\ReservationQueryRequest;
use App\Http\Requests\Reservation\ReservationStoreRequest;
use Spatie\RouteAttributes\Attributes\Middleware;
use App\Models\Reservation;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Reservation",
 *     description="API Endpoints for Reservation Management"
 * )
 */
#[Prefix('auth/reservations')]
#[Middleware('auth:api')]
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

        $perPage = $request->perPage();
        $paginator = $query->orderBy('reserved_at', 'desc')
            ->paginate($perPage);

        return $this->successResponse($paginator, 'Reservations retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/auth/reservations",
     *     tags={"Reservations"},
     *     summary="Tạo đặt bàn mới",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"guest_count", "reserved_at"},
     *             @OA\Property(property="guest_count", type="integer", example=4, description="Số lượng khách"),
     *             @OA\Property(property="note", type="string", example="Cần chỗ ngồi gần cửa sổ", description="Ghi chú đặt bàn"),
     *             @OA\Property(property="reserved_at", type="string", format="date-time", example="2025-10-15 19:00:00", description="Thời gian đặt bàn")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Đặt bàn thành công"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=429, description="Quá số lần đặt bàn cho phép trong 1 giờ")
     * )
     */
    #[Post('/', middleware: ['permission:reservations.create'])]
    public function store(ReservationStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $customerId = Customer::where('user_id', Auth::id())->value('id');

        if (!$customerId) {
            return $this->errorResponse(
                'Tài khoản chưa có hồ sơ khách hàng (customer). Vui lòng tạo Customer trước khi đặt bàn.',
                [],
                422
            );
        }
        $windowStart = now()->subHour();
        $recentCount = Reservation::where('customer_id', $customerId)
            ->where('created_at', '>=', $windowStart)
            ->count();

        if ($recentCount >= 3) {
            $firstInWindow = Reservation::where('customer_id', $customerId)
                ->where('created_at', '>=', $windowStart)
                ->orderBy('created_at', 'asc')
                ->first();

            $nextAllowedAt = $firstInWindow ? $firstInWindow->created_at->copy()->addHour() : now()->addHour();
            $retryAfterSec = max(0, now()->diffInSeconds($nextAllowedAt, false));

            return $this->errorResponse(
                'Bạn đã đặt bàn quá số lần cho phép trong 1 giờ. Vui lòng thử lại sau.',
                ['retry_after_seconds' => $retryAfterSec, 'next_allowed_at' => $nextAllowedAt->toDateTimeString()],
                429
            );
        }

        $notes = $data['notes'] ?? $request->input('note') ?? null;

        $reservation = Reservation::create([
            'customer_id'      => $customerId,
            'number_of_people' => $data['number_of_people'],
            'notes'            => $notes,
            'reserved_at'      => $data['reserved_at'],
            'status'           => 0,
            'created_by'       => Auth::id(),
        ]);

        return $this->successResponse($reservation->fresh(), 'Reservation created', 201);
    }

    /**
     * @OA\Get(
     *   path="/api/auth/reservations/my",
     *   tags={"Reservations"},
     *   summary="Lấy danh sách đặt bàn của người dùng hiện tại",
     *   @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="integer", enum={0,1,2,3})),
     *   @OA\Parameter(name="date_from", in="query", required=false, @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="date_to", in="query", required=false, @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="q", in="query", required=false, @OA\Schema(type="string")),
     *   @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=10)),
     *   @OA\Response(response=200, description="Danh sách reservation của tôi")
     * )
     */
    #[Get('/my')]
    public function my(ReservationQueryRequest $request): JsonResponse
    {
        $userId = Auth::id();
        $customerId = Customer::where('user_id', $userId)->value('id');

        if (!$customerId) {
            return $this->errorResponse('Không tìm thấy hồ sơ khách hàng cho tài khoản này.', [], 404);
        }

        $filters = $request->filters();
        $query = Reservation::with('customer')->where('customer_id', $customerId);

        if (!is_null($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('reserved_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('reserved_at', '<=', $filters['date_to']);
        }
        if (!empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function ($s) use ($q) {
                $s->where('id', 'like', "%{$q}%")
                ->orWhere('notes', 'like', "%{$q}%");
            });
        }

        $perPage = $request->perPage();
        $paginator = $query->orderBy('reserved_at', 'desc')->paginate($perPage);

        return $this->successResponse($paginator, 'My reservations retrieved successfully');
    }
}
