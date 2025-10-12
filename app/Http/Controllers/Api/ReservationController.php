<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reservation\ReservationQueryRequest;
use App\Models\Reservation;
use App\Models\TableSessionDiningTable;
use App\Models\TableSessionReservation;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Put;
use Illuminate\Http\Request;

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

        if (!empty($filters['reserved_at'])) {
            $query->where('reserved_at', '=', $filters['reserved_at']);
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
            $sessionId = null;

            if ($tableSessionReservation) {
                $sessionId = $tableSessionReservation->table_session_id;

                $tableSessionDining = TableSessionDiningTable::where('table_session_id', $sessionId)
                    ->with('diningTable:id') // chỉ lấy id của bàn
                    ->first();

                if ($tableSessionDining && $tableSessionDining->diningTable) {
                    $assigned = true;
                    $diningTableId  = $tableSessionDining->diningTable->id;
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
                'assigned' => $assigned,
            ];
        }

        return $this->successResponse($result, 'Check completed successfully.');
    }
}
