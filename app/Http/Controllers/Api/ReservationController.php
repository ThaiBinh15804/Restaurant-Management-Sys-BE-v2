<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reservation\ReservationQueryRequest;
use App\Http\Requests\Reservation\ReservationStoreRequest;
use Spatie\RouteAttributes\Attributes\Middleware;
use App\Models\Reservation;
use App\Models\TableSessionDiningTable;
use App\Models\TableSessionReservation;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Put;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Reservation",
 *     description="API Endpoints for Reservation Management"
 * )
 */
#[Prefix('reservations')]
class ReservationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/reservations",
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
        $filters = $request->filters();

        $query = Reservation::with('customer');

        if (!empty($filters['customer_name'])) {
            $query->whereHas('customer', function ($q) use ($filters) {
                $q->where('full_name', 'like', '%' . $filters['customer_name'] . '%');
            });
        }

        if (!empty($filters['customer_phone'])) {
            $query->whereHas('customer', function ($q) use ($filters) {
                $q->where('phone', 'like', '%' . $filters['customer_phone'] . '%');
            });
        }

        $from = $filters['reserved_at_from'] ?? null;
        $to   = $filters['reserved_at_to'] ?? null;

        if (!empty($from) && !empty($to)) {
            $query->whereBetween('reserved_at', [$from, $to]);
        }

        $reservations = $query->orderBy('reserved_at', 'desc')->get();

        return $this->successResponse($reservations, 'Reservations retrieved successfully');
    }

    /**
     * @OA\Put(
     *     path="/api/reservations/{id}/status",
     *     tags={"Reservations"},
     *     summary="Cập nhật trạng thái đặt bàn",
     *     description="Cho phép admin hoặc nhân viên có quyền thay đổi trạng thái của một đặt bàn (Pending, Confirmed, Cancelled, Completed)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID của reservation cần cập nhật",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(
     *                 property="status",
     *                 type="integer",
     *                 enum={0,1,2,3},
     *                 description="Trạng thái mới: 0=Pending, 1=Confirmed, 2=Cancelled, 3=Completed"
     *             ),
     *             @OA\Property(
     *                 property="notes",
     *                 type="string",
     *                 description="Ghi chú (tùy chọn)"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cập nhật trạng thái thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Không tìm thấy reservation",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Giá trị status không hợp lệ hoặc không thể cập nhật",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    #[Put('/{id}', middleware: ['permission:reservations.edit'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $reservation = Reservation::find($id);

        if (!$reservation) {
            return $this->errorResponse('Reservation not found.', 404);
        }

        // Validate linh hoạt: chỉ validate field nào có gửi xuống
        $validated = $request->validate([
            'status' => 'sometimes|integer|in:1,2,3',
            'reserved_at' => 'sometimes|date',
            'number_of_people' => 'sometimes|integer|min:1',
        ]);

        // Cập nhật các field có trong request
        foreach ($validated as $key => $value) {
            $reservation->$key = $value;
        }

        $reservation->save();

        return $this->successResponse(
            $reservation,
            'Reservation updated successfully.'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/reservations/check-assigned-tables",
     *     tags={"Reservations"},
     *     summary="Kiểm tra các đặt bàn đã được xếp bàn hay chưa",
     *     description="Lấy danh sách các reservation có status = 1 (Confirmed), kiểm tra xem đã có bàn được xếp hay chưa thông qua các bảng liên kết table_session_reservations và table_session_dining_table.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách reservation và trạng thái đã/ chưa xếp bàn",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Check completed successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="reservation_id", type="string", example="abc123"),
     *                     @OA\Property(property="customer_name", type="string", example="Nguyễn Văn A"),
     *                     @OA\Property(property="session_id", type="string", nullable=true, example="sess001"),
     *                     @OA\Property(property="table_name", type="string", nullable=true, example="Bàn 5"),
     *                     @OA\Property(property="assigned", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Không có quyền truy cập API này",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Forbidden")
     *         )
     *     )
     * )
     */
    #[Get('/check-assigned-tables', middleware: ['permission:reservations.view'])]
    public function checkAssignedTables(): JsonResponse
    {
        // Lấy tất cả reservation có status = 1 (Confirmed)
        $reservations = Reservation::with('customer')
            ->whereIn('status', [1, 3]) // confirmed và completed
            ->get();

        $result = [];

        foreach ($reservations as $reservation) {
            $tableSessionReservation = TableSessionReservation::where('reservation_id', $reservation->id)->first();

            $assigned = false;
            $diningTableId = null;
            $tableNumber = null;
            $sessionId = null;

            if ($tableSessionReservation) {
                $sessionId = $tableSessionReservation->table_session_id;

                $tableSessionDining = TableSessionDiningTable::where('table_session_id', $sessionId)
                    ->with('diningTable:id,table_number') // lấy cả id và số bàn
                    ->first();

                if ($tableSessionDining && $tableSessionDining->diningTable) {
                    $assigned = true;
                    $diningTableId  = $tableSessionDining->diningTable->id;
                    $tableNumber = $tableSessionDining->diningTable->table_number;
                }
            }

            $result[] = [
                'reservation_id' => $reservation->id,
                'reservation_status' => $reservation->status,
                'reservation_notes' => $reservation->notes,
                'reservation_reserved_at' => $reservation->reserved_at,
                'reservation_number_of_people' => $reservation->number_of_people,
                'customer_name' => $reservation->customer->full_name ?? null,
                'session_id' => $sessionId,
                'dining_table_id' => $diningTableId,
                'dining_table_number' => $tableNumber,
                'assigned' => $assigned,
            ];
        }

        return $this->successResponse($result, 'Check completed successfully.');
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
    #[Get('/my', middleware: ['auth:api'])]
    public function my(ReservationQueryRequest $request): JsonResponse
    {
        $userId = Auth::id();
        $customerId = Customer::where('user_id', $userId)->value('id');

        if (!$customerId) {
            return $this->errorResponse('Không tìm thấy hồ sơ khách hàng cho tài khoản này.', [], 404);
        }

        $filters = $request->filters();
        $query = Reservation::with('customer')->where('customer_id', $customerId);

        $status = $filters['status'] ?? null;
        if (!is_null($status)) {
            $query->where('status', $status);
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
