<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TableSession\MergeTablesRequest;
use App\Http\Requests\TableSession\SplitInvoiceRequest;
use App\Http\Requests\TableSession\SplitTableRequest;
use App\Http\Requests\TableSession\TableSessionQueryRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\TableSession;
use App\Models\TableSessionDiningTable;
use App\Models\TableSessionReservation;
use App\Models\User;
use App\Services\TableSessionService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Put;

#[Prefix('table-sessions')]
class TableSessionController extends Controller
{
    protected TableSessionService $tableSessionService;

    public function __construct(TableSessionService $tableSessionService)
    {
        $this->tableSessionService = $tableSessionService;
    }

    /**
     * @OA\Get(
     *     path="/api/table-sessions",
     *     tags={"TableSessions"},
     *     summary="Danh sách bàn + phiên bàn",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách bàn và phiên bàn",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="dining_table_id", type="integer"),
     *                 @OA\Property(property="table_number", type="string"),
     *                 @OA\Property(property="capacity", type="integer"),
     *                 @OA\Property(property="is_active", type="boolean"),
     *                 @OA\Property(property="session_id", type="integer", nullable=true),
     *                 @OA\Property(property="session_type", type="string", nullable=true),
     *                 @OA\Property(property="session_status", type="string", nullable=true),
     *                 @OA\Property(property="started_at", type="string", format="date-time", nullable=true),
     *                 @OA\Property(property="ended_at", type="string", format="date-time", nullable=true)
     *             )),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    #[Get('/', middleware: ['permission:table-sessions.view'])]
    public function index(TableSessionQueryRequest $request): JsonResponse
    {
        $filters = $request->filters();

        $query = DB::table('dining_tables as dt')
            ->leftJoinSub(function ($sub) {
                $sub->from('table_session_dining_table as tsdt')
                    ->join('table_sessions as ts', 'tsdt.table_session_id', '=', 'ts.id')
                    ->whereIn('ts.status', [1, 4])
                    ->select(
                        'tsdt.dining_table_id',
                        'ts.id',
                        'ts.type',
                        'ts.status',
                        'ts.started_at',
                        'ts.ended_at',
                        'ts.parent_session_id',
                        'ts.merged_into_session_id',
                    );
            }, 'ts', function ($join) {
                $join->on('dt.id', '=', 'ts.dining_table_id');
            })
            ->select(
                'dt.id as dining_table_id',
                'dt.table_number',
                'dt.capacity',
                'dt.is_active',
                'ts.id as session_id',
                'ts.type as session_type',
                'ts.status as session_status',
                'ts.started_at',
                'ts.ended_at',
                'ts.parent_session_id',
                'ts.merged_into_session_id',
            )
            ->orderBy('dt.table_number');

        // Lọc is_active
        if (!is_null($filters['is_active'])) {
            $query->where('dt.is_active', $filters['is_active']);
        }

        // Lọc capacity
        if (!is_null($filters['capacity'])) {
            $query->where('dt.capacity', $filters['capacity']);
        }

        // Lọc session_status
        if (!is_null($filters['session_status'])) {
            switch ($filters['session_status']) {
                case 'empty':
                    $query->whereNull('ts.id');
                    break;
                case 'pending':
                    $query->where('ts.status', 0);
                    break;
                case 'active':
                    $query->where('ts.status', 1);
                    break;
                case 'completed':
                    $query->where('ts.status', 2);
                    break;
                case 'cancelled':
                    $query->where('ts.status', 3);
                    break;
            }
        }

        $results = $query->get();

        return $this->successResponse($results, 'Table sessions retrieved successfully');
    }

    /**
     * @OA\Put(
     *     path="/api/table-sessions/{tableSession}",
     *     summary="Cập nhật trạng thái table session",
     *     description="Cập nhật trạng thái table session thành 'đang phục vụ' (1)",
     *     tags={"TableSessions"},
     *     @OA\Parameter(
     *         name="tableSession",
     *         in="path",
     *         description="ID của table session cần cập nhật",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cập nhật thành công",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="string", example="TS123"),
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="message", type="string", example="Table session status updated to active")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Table session không tồn tại",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Table session not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Lỗi server",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    #[Put('/{tableSession}', middleware: ['permission:table-sessions.edit'])]
    public function updateStatus(Request $request, string $tableSession): JsonResponse
    {
        $session = TableSession::find($tableSession);

        if (!$session) {
            return response()->json(['message' => 'Table session not found'], 404);
        }

        if (
            !$session->invoices()->where('status', '!=', Invoice::STATUS_PAID)->exists()
            && $session->started_at != null
        ) {
            $session->status = 2;
        } else {
            $session->status = 1; // Đang phục vụ

            // 🔹 Khi set phiên "đang phục vụ" → đánh dấu reservation liên quan là "hoàn thành"
            $reservations = TableSessionReservation::where('table_session_id', $session->id)
                ->with('reservation')
                ->get();

            foreach ($reservations as $tsr) {
                $reservation = $tsr->reservation;
                if ($reservation && $reservation->status == 1) { // 1 = đang chờ / đang đặt
                    $reservation->status = 3; // 3 = hoàn thành
                    $reservation->save();
                }
            }
        }
        $session->started_at = now();

        $session->save();
        return response()->json([
            'id' => $session->id,
            'status' => $session->status,
            'message' => 'Table session status updated to active'
        ], 200);
    }

    /**
     * @OA\Put(
     *     path="/api/table-sessions/{tableSession}/cancel",
     *     summary="Hủy phiên bàn (Table Session)",
     *     description="Cập nhật trạng thái của table session thành 'đã hủy' (3) và đồng thời cập nhật tất cả reservation liên quan cũng thành 'đã hủy' (3).",
     *     tags={"TableSessions"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="tableSession",
     *         in="path",
     *         description="ID của table session cần hủy",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Hủy phiên bàn thành công",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="string", example="TS123"),
     *             @OA\Property(property="status", type="integer", example=3),
     *             @OA\Property(property="message", type="string", example="Table session and related reservations cancelled successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Không tìm thấy table session",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Table session not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Lỗi server nội bộ",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    #[Put('/{tableSession}/cancel', middleware: ['permission:table-sessions.edit'])]
    public function cancelSession(Request $request, string $tableSession): JsonResponse
    {
        try {
            $session = TableSession::find($tableSession);

            if (!$session) {
                return response()->json(['message' => 'Table session not found'], 404);
            }

            // 🔹 Cập nhật trạng thái phiên bàn thành "đã hủy"
            $session->status = 3; // 3 = Cancelled
            $session->ended_at = now();
            $session->save();

            // 🔹 Lấy tất cả reservation liên quan và cập nhật status = 3
            $reservations = TableSessionReservation::where('table_session_id', $session->id)
                ->with('reservation')
                ->get();

            foreach ($reservations as $tsr) {
                $reservation = $tsr->reservation;
                if ($reservation && $reservation->status !== 3) {
                    $reservation->status = 3; // 3 = Cancelled
                    $reservation->save();
                }
            }

            return response()->json([
                'id' => $session->id,
                'status' => $session->status,
                'message' => 'Table session and related reservations cancelled successfully'
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Cancel table session failed', [
                'error' => $e->getMessage(),
                'table_session_id' => $tableSession
            ]);

            return response()->json(['message' => 'Internal server error'], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/table-sessions/reservation",
     *     summary="Tạo mới Table Session kiểu reservation",
     *     tags={"TableSessions"},
     *     description="Tạo một phiên bàn mới với type = 2 (reservation) và status = 0 (đang chờ), kèm liên kết với reservation.",
     *     operationId="createTableSession",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"customer_id","employee_id","reservation_id"},
     *             @OA\Property(property="customer_id", type="string", example="CUST123", description="ID của khách hàng"),
     *             @OA\Property(property="employee_id", type="string", example="EMP456", description="ID của nhân viên tạo phiên"),
     *             @OA\Property(property="reservation_id", type="string", example="RES789", description="ID của reservation liên kết")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tạo thành công",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="string", example="TS001"),
     *                 @OA\Property(property="type", type="integer", example=2),
     *                 @OA\Property(property="status", type="integer", example=0),
     *                 @OA\Property(property="customer_id", type="string", example="CUST123"),
     *                 @OA\Property(property="employee_id", type="string", example="EMP456"),
     *                 @OA\Property(property="reservation_id", type="string", example="RES789"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation lỗi",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="The customer_id field is required."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    #[Post('/reservation')]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'employee_id' => 'required|exists:employees,id',
            'reservation_id' => 'required|exists:reservations,id',
            'dining_table_id' => 'required|exists:dining_tables,id', // thêm validate cho bàn
            'pre_order' => 'nullable|string|in:yes,no', // 🟢 thêm validate cho pre_order
        ]);

        // Tạo TableSession
        $tableSession = TableSession::create([
            'type' => 2, // reservation
            'status' => 0, // đang chờ phiên
            'parent_session_id' => null,
            'merged_into_session_id' => null,
            'started_at' => null,
            'ended_at' => null,
            'customer_id' => $request->customer_id,
            'employee_id' => $request->employee_id,
            'created_by' => $request->employee_id,
            'updated_by' => $request->employee_id,
        ]);

        // Tạo liên kết với reservation trong bảng table_session_reservations
        TableSessionReservation::create([
            'table_session_id' => $tableSession->id,
            'reservation_id' => $request->reservation_id,
            'created_by' => $request->employee_id,
            'updated_by' => $request->employee_id,
        ]);

        // Liên kết với dining table
        TableSessionDiningTable::create([
            'table_session_id' => $tableSession->id,
            'dining_table_id' => $request->dining_table_id,
            'created_by' => $request->employee_id,
            'updated_by' => $request->employee_id,
        ]);

        // Update status của reservation thành 1 (đang gán vào phiên)
        $reservation = Reservation::find($request->reservation_id);
        if ($reservation) {
            $reservation->status = 1;
            $reservation->save();
        }

        // 🟢 Nếu pre_order = yes => tạo Order và lưu lại order_id
        $orderId = null;
        if ($request->pre_order === 'yes') {
            $order = Order::create([
                'table_session_id' => $tableSession->id,
                'status' => 0, // open
                'total_amount' => 0,
                'created_by' => $request->employee_id,
                'updated_by' => $request->employee_id,
            ]);

            $orderId = $order->id;
        }

        // ✅ Trả về cả session và order_id nếu có
        return response()->json([
            'success' => true,
            'data' => [
                'table_session' => $tableSession,
                'order_id' => $orderId,
            ],
        ], 200);
    }

    #[Post('/offline', middleware: ['permission:table-sessions.create'])]
    public function createTableSessionOffline(Request $request)
    {
        $data = $request->validate([
            'dining_table_id' => 'required|string|exists:dining_tables,id',
            'employee_id' => 'required|exists:employees,id',
        ]);

        $user = User::where('email', 'customerOffline@restaurant.com')->first();
        if (!$user) {
            return response()->json(['message' => 'Offline customer user not found'], 404);
        }

        $customer = Customer::where('user_id', $user->id)->first();

        if (!$customer) {
            return response()->json(['message' => 'Customer record not found for offline user'], 404);
        }

        $diningTableId = $data['dining_table_id'];

        // 🧩 1. Tạo session mới
        $session = TableSession::create([
            'type' => 0, // Offline
            'status' => 0, // Pending
            'parent_session_id' => null,
            'merged_into_session_id' => null,
            'started_at' => null,
            'ended_at' => null,
            'customer_id' => $customer->id, // từ bảng customers
            'employee_id' => $request->employee_id,
            'created_by' => $request->employee_id,
            'updated_by' => $request->employee_id,
        ]);

        // 🧩 2. Gắn vào bảng table_session_dining_table
        TableSessionDiningTable::create([
            'dining_table_id' => $diningTableId,
            'table_session_id' => $session->id,
            'created_by' => $request->employee_id,
            'updated_by' => $request->employee_id,
        ]);

        // 🧩 3. Trả về thông tin session vừa tạo
        return response()->json([
            'message' => 'Successfully created offline session',
            'data' => $session
        ], 201);
    }

    /**
     * - Có trong bảng table_session_dining_table
     * - Và table_session.status IN (1, 2)
     * - Và (
     *     (ended_at IS NULL AND started_at <= reserved_at)
     *     OR
     *     (started_at <= reserved_at AND ended_at >= reserved_at)
     *   )
     */

    /**
     * @OA\Get(
     *     path="/api/table-sessions/available-tables",
     *     tags={"TableSessions"},
     *     summary="Lấy danh sách bàn trống cho thời gian đặt cụ thể",
     *     description="Trả về danh sách các bàn còn trống dựa trên thời gian đặt (reserved_at) và số lượng khách (number_of_people). Một bàn được xem là bận nếu có phiên bàn đang hoạt động (status IN 1,2) và thời gian đặt nằm trong khoảng phục vụ của bàn.",
     *     operationId="getAvailableTables",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="reserved_at",
     *         in="query",
     *         required=true,
     *         description="Thời gian đặt bàn (YYYY-MM-DD HH:mm:ss)",
     *         @OA\Schema(type="string", format="date-time", example="2025-10-07 13:00:00")
     *     ),
     *     @OA\Parameter(
     *         name="number_of_people",
     *         in="query",
     *         required=true,
     *         description="Số lượng khách",
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách bàn trống",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="string"),
     *                 @OA\Property(property="table_number", type="integer"),
     *                 @OA\Property(property="capacity", type="integer"),
     *                 @OA\Property(property="is_active", type="boolean")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Thiếu hoặc sai dữ liệu đầu vào",
     *         @OA\JsonContent(@OA\Property(property="message", type="string"))
     *     )
     * )
     */
    #[Get('/available-tables', middleware: ['permission:table-sessions.view'])]
    public function getAvailableTables(Request $request): JsonResponse
    {
        $reservedAtRaw = $request->query('reserved_at');
        $numberOfPeople = (int) $request->query('number_of_people');

        if (empty($reservedAtRaw) || $numberOfPeople < 1) {
            return $this->errorResponse('Thiếu reserved_at hoặc number_of_people', 400);
        }

        try {
            // Chuyển về múi giờ Việt Nam cho đồng bộ
            $reservedAt = \Carbon\Carbon::parse($reservedAtRaw)
                ->setTimezone('Asia/Ho_Chi_Minh')
                ->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return $this->errorResponse('Định dạng thời gian không hợp lệ', 400);
        }

        // 🟢 Lấy toàn bộ bàn
        $tables = DB::table('dining_tables as dt')
            ->select('dt.id', 'dt.table_number', 'dt.capacity', 'dt.is_active')
            ->orderBy('dt.table_number')
            ->get();

        // 1. Lấy tất cả session đang bận (Pending/Active) tại thời điểm $reservedAt
        $busySessionIds = DB::table('table_sessions as ts')
            ->leftJoin('table_session_reservations as tsr', 'ts.id', '=', 'tsr.table_session_id')
            ->leftJoin('reservations as r', 'r.id', '=', 'tsr.reservation_id')
            ->whereIn('ts.status', [0, 1])
            ->where(function ($q) use ($reservedAt) {
                $q->where(function ($q2) use ($reservedAt) {
                    $q2->whereNull('ts.ended_at')
                        ->whereRaw(
                            '? >= COALESCE(r.reserved_at, ts.started_at)
                            AND ? < DATE_ADD(COALESCE(r.reserved_at, ts.started_at), INTERVAL 2 HOUR)',
                            [$reservedAt, $reservedAt]
                        );
                })
                    ->orWhere(function ($q2) use ($reservedAt) {
                        $q2->whereNotNull('ts.ended_at')
                            ->whereRaw('? BETWEEN COALESCE(r.reserved_at, ts.started_at) AND ts.ended_at', [$reservedAt]);
                    });
            })
            ->pluck('ts.id')
            ->toArray();

        // 2. Lấy tất cả bàn chính đang bận
        $mainTables = DB::table('table_session_dining_table as tsdt')
            ->join('table_sessions as ts', 'tsdt.table_session_id', '=', 'ts.id')
            ->whereIn('ts.id', $busySessionIds)
            ->pluck('tsdt.dining_table_id')
            ->toArray();

        // 3. Lấy tất cả bàn phụ gộp vào các session chính đang bận
        $mergedTables = DB::table('table_session_dining_table as tsdt')
            ->join('table_sessions as ts', 'tsdt.table_session_id', '=', 'ts.id')
            ->whereIn('ts.merged_into_session_id', $busySessionIds)
            ->pluck('tsdt.dining_table_id')
            ->toArray();

        // 4. Gộp lại tất cả bàn bận
        $busyTables = array_unique(array_merge($mainTables, $mergedTables));

        // 🟡 Gắn trạng thái cho từng bàn
        $result = $tables->map(function ($table) use ($busyTables, $numberOfPeople) {
            $isBusy = in_array($table->id, $busyTables, true);
            $isValidCapacity = $table->capacity >= $numberOfPeople;
            $isActive = (bool) $table->is_active;

            $table->table_available = $isActive && $isValidCapacity && !$isBusy;
            $table->reason = match (true) {
                !$isActive => 'Bàn đang ngưng hoạt động',
                !$isValidCapacity => 'Sức chứa không đủ',
                $isBusy => 'Bàn đang bận',
                default => 'Bàn hợp lệ để xếp'
            };

            return $table;
        });

        return $this->successResponse($result, 'Danh sách bàn và trạng thái khả dụng được lấy thành công.');
    }

    /**
     * @OA\Get(
     *     path="/api/table-sessions/{idDiningTable}",
     *     tags={"TableSessions"},
     *     summary="Lấy thông tin phiên bàn theo Dining Table ID",
     *     description="Trả về phiên bàn (status = 1 - đang phục vụ) kèm thông tin đặt chỗ và khách hàng",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idDiningTable",
     *         in="path",
     *         required=true,
     *         description="ID của Dining Table (ví dụ: DT4PP4X0GT)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Thông tin phiên bàn lấy thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Table session retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="dining_table_id", type="string", example="DT4PP4X0GT"),
     *                 @OA\Property(property="session_id", type="string", example="TS12345"),
     *                 @OA\Property(property="session_type", type="integer", example=0, description="0=Offline, 1=Online"),
     *                 @OA\Property(property="session_status", type="integer", example=1, description="1=Active"),
     *                 @OA\Property(property="started_at", type="string", format="date-time", example="2025-10-02T08:30:00Z"),
     *                 @OA\Property(property="ended_at", type="string", format="date-time", nullable=true),
     *                 @OA\Property(property="reservation_number_of_people", type="integer", example=2),
     *                 @OA\Property(property="reservation_notes", type="string", example="Near window"),
     *                 @OA\Property(property="customer_id", type="string", example="CUS001"),
     *                 @OA\Property(property="customer_name", type="string", example="Nguyen Van A"),
     *                 @OA\Property(property="customer_gender", type="string", example="male"),
     *                 @OA\Property(property="customer_phone", type="string", example="0901234567"),
     *                 @OA\Property(property="customer_address", type="string", example="123 Le Loi, District 1, HCMC")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Không tìm thấy phiên bàn đang phục vụ",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Không tìm thấy phiên bàn đang phục vụ cho Dining Table: DT4PP4X0GT")
     *         )
     *     )
     * )
     */
    #[Get('/{idDiningTable}', middleware: ['permission:table-sessions.view'])]
    public function getByDiningTable(string $idDiningTable): JsonResponse
    {
        $session = DB::table('table_session_dining_table as tsdt')
            ->join('table_sessions as ts', 'tsdt.table_session_id', '=', 'ts.id')
            ->leftJoin('table_session_reservations as tsr', 'ts.id', '=', 'tsr.table_session_id')
            ->leftJoin('reservations as r', 'tsr.reservation_id', '=', 'r.id')
            ->leftJoin('customers as c', 'c.id', '=', 'r.customer_id')
            ->where('tsdt.dining_table_id', $idDiningTable)
            ->where('ts.status', 1) // chỉ lấy phiên đang phục vụ
            ->select(
                'tsdt.dining_table_id',
                'ts.id as session_id',
                'ts.type as session_type',
                'ts.status as session_status',
                'ts.started_at',
                'ts.ended_at',
                'r.number_of_people as reservation_number_of_people',
                'r.notes as reservation_notes',
                'r.reserved_at as reservation_reserved_at',
                'c.id as customer_id',
                'c.full_name as customer_name',
                'c.gender as customer_gender',
                'c.phone as customer_phone',
                'c.address as customer_address'
            )
            ->first(); // vì mỗi bàn chỉ có tối đa 1 phiên active

        if (!$session) {
            return $this->errorResponse(
                'No session found for Dining Table: ' . $idDiningTable,
                404
            );
        }

        if (!$session->customer_id) {
            $session->customer_id = 'guest';
            $session->customer_name = 'Khách vãng lai';
            $session->customer_gender = null;
            $session->customer_phone = null;
            $session->customer_address = null;
        }

        return $this->successResponse(
            $session,
            'Table session retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/table-sessions/{idDiningTable}/session-pending",
     *     summary="Lấy các phiên bàn đang chờ theo Dining Table",
     *     tags={"DiningTables"},
     *     description="Trả về danh sách các phiên bàn có trạng thái đang chờ (status = 0), kèm thông tin reservation nếu có",
     *     operationId="getActiveTableSessions",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="idDiningTable",
     *         in="path",
     *         description="ID của dining table",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Active sessions retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="session_id", type="string"),
     *                 @OA\Property(property="table_id", type="string"),
     *                 @OA\Property(property="table_number", type="string"),
     *                 @OA\Property(property="session_type", type="integer", description="0-Offline,1-Merge,2-Reservation,3-Split"),
     *                 @OA\Property(property="session_status", type="integer", description="0-Active"),
     *                 @OA\Property(property="started_at", type="string", format="date-time"),
     *                 @OA\Property(property="customer_id", type="string"),
     *                 @OA\Property(property="employee_id", type="string"),
     *                 @OA\Property(
     *                     property="reservation",
     *                     type="object",
     *                     @OA\Property(property="reservation_id", type="string"),
     *                     @OA\Property(property="reservation_customer_id", type="string"),
     *                     @OA\Property(property="reservation_time", type="string", format="date-time"),
     *                     @OA\Property(property="number_of_people", type="integer"),
     *                     @OA\Property(property="reservation_status", type="integer", description="0-Pending,1-Confirmed,..."),
     *                     @OA\Property(property="notes", type="string", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Không tìm thấy phiên bàn đang hoạt động",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    #[Get('/{idDiningTable}/session-pending', middleware: ['permission:table-sessions.view'])]
    public function getActiveTableSessions(string $idDiningTable): JsonResponse
    {
        $sessions = DB::table('dining_tables as dt')
            ->leftJoin('table_session_dining_table as tsdt', 'tsdt.dining_table_id', '=', 'dt.id')
            ->leftJoin('table_sessions as ts', 'ts.id', '=', 'tsdt.table_session_id')
            ->leftJoin('table_session_reservations as tsr', 'tsr.table_session_id', '=', 'ts.id')
            ->leftJoin('reservations as r', 'r.id', '=', 'tsr.reservation_id')
            ->leftJoin('customers as c', 'c.id', '=', 'r.customer_id')
            ->where('dt.id', $idDiningTable)
            ->where('ts.status', 0) // chỉ lấy phiên đang hoạt động
            ->select(
                'dt.id as dining_table_id',
                'dt.table_number',
                'dt.capacity',
                'dt.is_active',
                'ts.id as session_id',
                'ts.type as session_type',
                'ts.status as session_status',
                'ts.started_at',
                'ts.customer_id',
                'ts.employee_id',
                'r.id as reservation_id',
                'r.customer_id as reservation_customer_id',
                'r.reserved_at as reservation_time',
                'r.number_of_people as reservation_number_of_people',
                'r.status as reservation_status',
                'r.notes as reservation_notes',
                'c.full_name as customer_name',
                'c.phone as customer_phone',
                'c.gender as customer_gender',
                'c.address as customer_address'
            )
            ->orderBy('r.reserved_at', 'asc')
            ->get();

        if ($sessions->isEmpty()) {
            return $this->errorResponse(
                'Không tìm thấy phiên bàn đang hoạt động cho Dining Table: ' . $idDiningTable,
                404
            );
        }

        $formatted = $sessions->map(function ($session) {
            return [
                'session_id' => $session->session_id,
                'table_id' => $session->dining_table_id,
                'table_number' => $session->table_number,
                'table_capacity' => $session->capacity,
                'session_type' => $session->session_type,
                'session_status' => $session->session_status,
                'started_at' => $session->started_at,
                'customer_id' => $session->customer_id,
                'employee_id' => $session->employee_id,
                'reservation' => [
                    'reservation_id' => $session->reservation_id,
                    'customer_id' => $session->reservation_customer_id,
                    'reserved_at' => $session->reservation_time,
                    'number_of_people' => $session->reservation_number_of_people,
                    'status' => $session->reservation_status,
                    'notes' => $session->reservation_notes,
                    'customer_name' => $session->customer_name,
                    'customer_phone' => $session->customer_phone,
                    'customer_gender' => $session->customer_gender,
                    'customer_address' => $session->customer_address
                ]
            ];
        });

        return $this->successResponse(
            $formatted,
            'Active sessions retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/table-sessions/{tableSessionId}/orders",
     *     tags={"TableSessions"},
     *     summary="Lấy danh sách Order theo Table Session ID",
     *     description="Trả về danh sách order và chi tiết order_items kèm thông tin món ăn và category",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="tableSessionId",
     *         in="path",
     *         required=true,
     *         description="ID của Table Session (ví dụ: TSPC3JAEON)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Danh sách order lấy thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Orders retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="order_id", type="string", example="O123"),
     *                     @OA\Property(property="table_session_id", type="string", example="TSPC3JAEON"),
     *                     @OA\Property(property="order_status", type="integer", example=1),
     *                     @OA\Property(property="total_amount", type="number", example=350000),
     *                     @OA\Property(
     *                         property="items",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="order_item_id", type="string", example="OI001"),
     *                             @OA\Property(property="quantity", type="integer", example=2),
     *                             @OA\Property(property="item_price", type="number", example=120000),
     *                             @OA\Property(property="total_price", type="number", example=240000),
     *                             @OA\Property(property="item_status", type="integer", example=1),
     *                             @OA\Property(property="notes", type="string", example="Less spicy"),
     *                             @OA\Property(
     *                                 property="dish",
     *                                 type="object",
     *                                 @OA\Property(property="dish_id", type="string", example="D123"),
     *                                 @OA\Property(property="dish_name", type="string", example="Fried Rice"),
     *                                 @OA\Property(property="category_name", type="string", example="Main Course")
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Không tìm thấy orders cho Table Session",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Không tìm thấy orders cho Table Session: TSPC3JAEON")
     *         )
     *     )
     * )
     */
    #[Get('/{tableSessionId}/orders', middleware: ['permission:table-sessions.view'])]
    public function getOrdersBySession(string $tableSessionId): JsonResponse
    {
        $orders = DB::table('orders as o')
            ->leftJoin('order_items as oi', 'o.id', '=', 'oi.order_id')
            ->leftJoin('dishes as d', 'oi.dish_id', '=', 'd.id')
            ->leftJoin('dish_categories as dc', 'd.category_id', '=', 'dc.id')
            ->where('o.table_session_id', $tableSessionId)
            ->select(
                'o.id as order_id',
                'o.table_session_id',
                'o.status as order_status',
                'o.total_amount',
                'oi.id as order_item_id',
                'oi.quantity',
                'oi.price as item_price',
                'oi.total_price',
                'oi.status as item_status',
                'oi.notes',
                'oi.prepared_by',
                'oi.served_at',
                'oi.created_at',
                'oi.cancelled_reason',
                'd.id as dish_id',
                'd.name as dish_name',
                'd.price as dish_price',
                'd.desc as dish_desc',
                'd.cooking_time',
                'd.image',
                'd.is_active as dish_active',
                'dc.name as category_name',
                'dc.desc as category_desc'
            )
            ->orderBy('o.created_at', 'asc')
            ->get();

        if ($orders->isEmpty()) {
            return $this->errorResponse(
                'Không tìm thấy orders cho Table Session: ' . $tableSessionId,
                404
            );
        }

        $grouped = $orders->groupBy('order_id')->map(function ($rows) {
            $order = $rows->first();
            return [
                'order_id' => $order->order_id,
                'table_session_id' => $order->table_session_id,
                'order_status' => (int) $order->order_status,
                'total_amount' => (float) $order->total_amount,
                'items' => $rows->filter(fn($r) => $r->order_item_id !== null)->map(function ($r) {
                    return [
                        'order_item_id' => $r->order_item_id,
                        'quantity' => (int) $r->quantity,
                        'item_price' => (float) $r->item_price,
                        'total_price' => (float) $r->total_price,
                        'item_status' => (int) $r->item_status,
                        'notes' => $r->notes,
                        'prepared_by' => $r->prepared_by,
                        'served_at' => $r->served_at,
                        'created_at' => $r->created_at,
                        'cancelled_reason' => $r->cancelled_reason,
                        'dish' => [
                            'dish_id' => $r->dish_id,
                            'dish_name' => $r->dish_name,
                            'dish_price' => (float) $r->dish_price,
                            'dish_desc' => $r->dish_desc,
                            'cooking_time' => (int) $r->cooking_time,
                            'image' => $r->image,
                            'dish_active' => (bool) $r->dish_active,
                            'category_name' => $r->category_name,
                            'category_desc' => $r->category_desc,
                        ]
                    ];
                })->values()
            ];
        })->values();

        return $this->successResponse(
            $grouped,
            'Orders retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/table-sessions/{idDiningTable}/session-history/{sessionId}",
     *     tags={"TableSessions"},
     *     summary="Xem lịch sử chi tiết một phiên bàn",
     *     description="Trả về tất cả thông tin liên quan tới một phiên bàn: bàn, reservation, khách hàng, orders và món ăn",
     *     operationId="getTableSessionDetail",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="idDiningTable",
     *         in="path",
     *         description="ID của bàn",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sessionId",
     *         in="path",
     *         description="ID của phiên bàn",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chi tiết phiên bàn",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Không tìm thấy phiên bàn",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Không tìm thấy phiên bàn")
     *         )
     *     )
     * )
     */
    #[Get('/{idDiningTable}/session-history/{sessionId}', middleware: ['permission:table-sessions.view'])]
    public function getTableSessionDetail(string $idDiningTable, string $sessionId): JsonResponse
    {
        $sessions = DB::table('dining_tables as dt')
            ->leftJoin('table_session_dining_table as tsdt', 'tsdt.dining_table_id', '=', 'dt.id')
            ->leftJoin('table_sessions as ts', 'ts.id', '=', 'tsdt.table_session_id')
            ->leftJoin('table_session_reservations as tsr', 'tsr.table_session_id', '=', 'ts.id')
            ->leftJoin('reservations as r', 'r.id', '=', 'tsr.reservation_id')
            ->leftJoin('customers as c', 'c.id', '=', 'r.customer_id')
            ->leftJoin('orders as o', 'o.table_session_id', '=', 'ts.id')
            ->leftJoin('order_items as oi', 'oi.order_id', '=', 'o.id')
            ->leftJoin('dishes as d', 'd.id', '=', 'oi.dish_id')
            ->where('dt.id', $idDiningTable)
            ->where('ts.id', $sessionId)
            ->whereIn('ts.status', [0, 2, 3])
            ->select(
                'dt.id as dining_table_id',
                'dt.table_number',
                'dt.capacity',
                'dt.is_active',
                'ts.id as session_id',
                'ts.type as session_type',
                'ts.status as session_status',
                'ts.started_at',
                'ts.ended_at',
                'ts.customer_id',
                'ts.employee_id',
                'r.id as reservation_id',
                'r.customer_id as reservation_customer_id',
                'r.reserved_at as reservation_time',
                'r.number_of_people as reservation_number_of_people',
                'r.status as reservation_status',
                'r.notes as reservation_notes',
                'c.full_name as customer_name',
                'c.phone as customer_phone',
                'c.gender as customer_gender',
                'c.address as customer_address',
                'o.id as order_id',
                'o.status as order_status',
                'o.total_amount as order_total_amount',
                'oi.id as order_item_id',
                'oi.quantity as item_quantity',
                'oi.price as item_price',
                'oi.total_price as item_total_price',
                'oi.status as item_status',
                'oi.notes as item_notes',
                'd.id as dish_id',
                'd.name as dish_name',
                'd.price as dish_price',
                'd.desc as dish_desc',
                'd.cooking_time',
                'd.image as dish_image'
            )
            ->orderBy('ts.started_at', 'desc')
            ->orderBy('o.id')
            ->orderBy('oi.id')
            ->get();

        if ($sessions->isEmpty()) {
            return $this->errorResponse(
                "Không tìm thấy phiên bàn cho Dining Table: $idDiningTable, Session: $sessionId",
                404
            );
        }

        // Group dữ liệu
        $grouped = $sessions->groupBy('session_id')->map(function ($sessionRows) {
            $session = $sessionRows->first();

            $reservation = [
                'reservation_id' => $session->reservation_id,
                'customer_id' => $session->reservation_customer_id,
                'reserved_at' => $session->reservation_time,
                'number_of_people' => $session->reservation_number_of_people,
                'status' => $session->reservation_status,
                'notes' => $session->reservation_notes,
                'customer_name' => $session->customer_name,
                'customer_phone' => $session->customer_phone,
                'customer_gender' => $session->customer_gender,
                'customer_address' => $session->customer_address
            ];

            $orders = $sessionRows->groupBy('order_id')->map(function ($orderRows) {
                $order = $orderRows->first();
                $items = $orderRows->map(function ($r) {
                    if (!$r->order_item_id) return null;
                    return [
                        'order_item_id' => $r->order_item_id,
                        'quantity' => $r->item_quantity,
                        'price' => $r->item_price,
                        'total_price' => $r->item_total_price,
                        'status' => $r->item_status,
                        'notes' => $r->item_notes,
                        'dish' => [
                            'dish_id' => $r->dish_id,
                            'name' => $r->dish_name,
                            'price' => $r->dish_price,
                            'desc' => $r->dish_desc,
                            'cooking_time' => $r->cooking_time,
                            'image' => $r->dish_image
                        ]
                    ];
                })->filter()->values();

                return [
                    'order_id' => $order->order_id,
                    'status' => $order->order_status,
                    'total_amount' => $order->order_total_amount,
                    'items' => $items
                ];
            })->values();

            return [
                'session_id' => $session->session_id,
                'table_id' => $session->dining_table_id,
                'table_number' => $session->table_number,
                'table_capacity' => $session->capacity,
                'session_type' => $session->session_type,
                'session_status' => $session->session_status,
                'started_at' => $session->started_at,
                'ended_at' => $session->ended_at,
                'customer_id' => $session->customer_id,
                'employee_id' => $session->employee_id,
                'reservation' => $reservation,
                'orders' => $orders
            ];
        })->values();

        return $this->successResponse(
            $grouped->first(),
            'Chi tiết phiên bàn retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/table-sessions/merge",
     *     tags={"TableSessions"},
     *     summary="Gộp nhiều bàn vào một bàn chính",
     *     description="Gộp nhiều table sessions vào một target session, tạo invoice tổng và chuyển tất cả orders",
     *     operationId="mergeTables",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"source_session_ids", "target_session_id", "employee_id"},
     *             @OA\Property(
     *                 property="source_session_ids",
     *                 type="array",
     *                 description="Danh sách ID các session cần gộp",
     *                 @OA\Items(type="string", example="TS001")
     *             ),
     *             @OA\Property(
     *                 property="target_session_id",
     *                 type="string",
     *                 description="ID session đích (bàn chính)",
     *                 example="TS002"
     *             ),
     *             @OA\Property(
     *                 property="employee_id",
     *                 type="string",
     *                 description="ID nhân viên thực hiện",
     *                 example="EMP001"
     *             ),
     *             @OA\Property(
     *                 property="note",
     *                 type="string",
     *                 description="Ghi chú (tùy chọn)",
     *                 example="Gộp bàn theo yêu cầu khách"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Gộp bàn thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tables merged successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="merged_invoice", type="object"),
     *                 @OA\Property(property="merged_from_sessions", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="target_session", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation failed hoặc không thể gộp",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Lỗi server",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    #[Post('/merge', middleware: ['permission:table-sessions.view'])]
    public function mergeTables(MergeTablesRequest $request): JsonResponse
    {
        $result = $this->tableSessionService->mergeTables(
            $request->validated()['source_session_ids'],
            $request->validated()['target_session_id'],
            $request->validated()['employee_id']
        );

        if (!$result['success']) {
            return $this->errorResponse(
                $result['message'],
                $result['errors'] ?? [],
                400
            );
        }

        return $this->successResponse(
            $result['data'],
            $result['message']
        );
    }

    /**
     * @OA\Post(
     *     path="/api/table-sessions/split-invoice",
     *     tags={"TableSessions"},
     *     summary="Tách hóa đơn theo tỷ lệ phần trăm",
     *     description="Chia một invoice thành nhiều invoice con theo tỷ lệ % của số tiền còn lại chưa thanh toán. Tổng % các phần tách phải < 100%, phần còn lại sẽ ở invoice gốc.",
     *     operationId="splitInvoice",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"invoice_id", "splits", "employee_id"},
     *             @OA\Property(
     *                 property="invoice_id",
     *                 type="string",
     *                 description="ID hóa đơn cần tách",
     *                 example="IN001"
     *             ),
     *             @OA\Property(
     *                 property="splits",
     *                 type="array",
     *                 description="Danh sách các phần tách theo %. Tổng % phải < 100%",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"percentage"},
     *                     @OA\Property(
     *                         property="percentage",
     *                         type="number",
     *                         format="float",
     *                         description="Tỷ lệ % cần tách (0.01 - 99.99). VD: 40 = 40%",
     *                         example=40.0,
     *                         minimum=0.01,
     *                         maximum=99.99
     *                     ),
     *                     @OA\Property(
     *                         property="note",
     *                         type="string",
     *                         nullable=true,
     *                         description="Ghi chú cho phần tách",
     *                         example="Hóa đơn khách A"
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="employee_id",
     *                 type="string",
     *                 description="ID nhân viên thực hiện",
     *                 example="EMP001"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tách hóa đơn thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoice split successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="parent_invoice", type="object", description="Hóa đơn gốc sau khi tách (chứa phần còn lại)"),
     *                 @OA\Property(
     *                     property="child_invoices",
     *                     type="array",
     *                     description="Danh sách hóa đơn con được tách ra",
     *                     @OA\Items(type="object")
     *                 ),
     *                 @OA\Property(
     *                     property="summary",
     *                     type="object",
     *                     @OA\Property(property="original_remaining", type="number", format="float", description="Số tiền còn lại ban đầu"),
     *                     @OA\Property(property="split_count", type="integer", description="Số hóa đơn con được tạo"),
     *                     @OA\Property(property="total_split_percentage", type="number", format="float", description="Tổng % đã tách"),
     *                     @OA\Property(property="parent_remaining_percentage", type="number", format="float", description="% còn lại ở hóa đơn gốc"),
     *                     @OA\Property(property="verification", type="string", example="passed")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation failed hoặc không thể tách",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Total percentage must be less than 100%"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Invoice không tồn tại",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invoice not found")
     *         )
     *     )
     * )
     */
    #[Post('/split-invoice', middleware: ['permission:table-sessions.split'])]
    public function splitInvoice(SplitInvoiceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->tableSessionService->splitInvoice(
            $validated['invoice_id'],
            $validated['splits'],
            $validated['employee_id']
        );

        if (!$result['success']) {
            return $this->errorResponse(
                $result['message'],
                $result['errors'] ?? [],
                400
            );
        }

        return $this->successResponse(
            $result['data'],
            $result['message']
        );
    }

    /**
     * @OA\Post(
     *     path="/api/table-sessions/split-table",
     *     tags={"TableSessions"},
     *     summary="Tách bàn - Di chuyển món ăn giữa các bàn",
     *     description="Di chuyển một hoặc nhiều món ăn từ bàn nguồn sang bàn đích. Hoạt động ở cấp độ order items, không bắt buộc phải có invoice. Nếu có invoice thì sẽ cập nhật số tiền, nếu chưa có thì chỉ chuyển món.",
     *     operationId="splitTable",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"source_session_id", "order_items", "employee_id"},
     *             @OA\Property(
     *                 property="source_session_id",
     *                 type="string",
     *                 description="ID của session nguồn (bàn cần tách)",
     *                 example="TS001"
     *             ),
     *             @OA\Property(
     *                 property="order_items",
     *                 type="array",
     *                 description="Danh sách món cần tách",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"order_item_id", "quantity_to_transfer"},
     *                     @OA\Property(property="order_item_id", type="string", example="OI001"),
     *                     @OA\Property(property="quantity_to_transfer", type="integer", example=2, description="Số lượng cần tách")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="target_session_id",
     *                 type="string",
     *                 nullable=true,
     *                 description="ID session đích (nếu chuyển sang bàn có sẵn)",
     *                 example="TS002"
     *             ),
     *             @OA\Property(
     *                 property="target_dining_table_id",
     *                 type="string",
     *                 nullable=true,
     *                 description="ID bàn đích (nếu tạo session mới). Required nếu không có target_session_id",
     *                 example="DT003"
     *             ),
     *             @OA\Property(
     *                 property="note",
     *                 type="string",
     *                 nullable=true,
     *                 description="Ghi chú",
     *                 example="Khách yêu cầu tách bàn"
     *             ),
     *             @OA\Property(
     *                 property="employee_id",
     *                 type="string",
     *                 description="ID nhân viên thực hiện",
     *                 example="EMP001"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tách bàn thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Table split successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="source_session", type="object"),
     *                 @OA\Property(property="target_session", type="object"),
     *                 @OA\Property(property="source_invoice", type="object", nullable=true, description="Null nếu chưa có invoice"),
     *                 @OA\Property(property="target_invoice", type="object", nullable=true, description="Null nếu chưa có invoice"),
     *                 @OA\Property(
     *                     property="summary",
     *                     type="object",
     *                     @OA\Property(property="transferred_amount", type="number", format="float"),
     *                     @OA\Property(property="items_transferred", type="integer"),
     *                     @OA\Property(property="source_remaining", type="number", format="float", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error hoặc logic error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Session không tồn tại",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    #[Post('/split-table', middleware: ['permission:table-sessions.split'])]
    public function splitTable(SplitTableRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->tableSessionService->splitTable(
            $validated['source_session_id'],
            $validated['order_items'],
            $validated['target_session_id'] ?? null,
            $validated['target_dining_table_id'] ?? null,
            $validated['employee_id'],
            $validated['note'] ?? null
        );

        if (!$result['success']) {
            return $this->errorResponse(
                $result['message'],
                $result['errors'] ?? [],
                400
            );
        }

        return $this->successResponse(
            $result['data'],
            $result['message']
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/table-sessions/unmerge/{mergedSessionId}",
     *     tags={"TableSessions"},
     *     summary="Hủy gộp bàn (rollback merge)",
     *     description="Khôi phục các session đã được gộp về trạng thái trước khi gộp",
     *     operationId="unmergeTables",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="mergedSessionId",
     *         in="path",
     *         description="ID của session đã gộp",
     *         required=true,
     *         @OA\Schema(type="string", example="TS002")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"employee_id"},
     *             @OA\Property(
     *                 property="employee_id",
     *                 type="string",
     *                 description="ID nhân viên thực hiện",
     *                 example="EMP001"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Hủy gộp thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tables unmerged successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="restored_sessions",
     *                     type="array",
     *                     @OA\Items(type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Không thể hủy gộp",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Session không tồn tại",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    #[Delete('/unmerge/{mergedSessionId}', middleware: ['permission:table-sessions.unmerge'])]
    public function unmergeTables(string $mergedSessionId, Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|string|exists:employees,id'
        ]);

        $result = $this->tableSessionService->unmerge(
            $mergedSessionId,
            $request->input('employee_id')
        );

        if (!$result['success']) {
            return $this->errorResponse(
                $result['message'],
                $result['errors'] ?? [],
                400
            );
        }

        return $this->successResponse(
            $result['data'],
            $result['message']
        );
    }

    /**
     * @OA\Get(
     *     path="/api/table-sessions/{tableSessionId}/invoice-summary",
     *     tags={"TableSessions"},
     *     summary="Tóm tắt thông tin hóa đơn của table session",
     *     description="Trả về tổng quan các hóa đơn liên quan đến table session: tổng số hóa đơn, tổng tiền, số tiền còn lại, số hóa đơn chưa thanh toán, thanh toán một phần, và đã hoàn tất thanh toán",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="tableSessionId",
     *         in="path",
     *         required=true,
     *         description="ID của Table Session",
     *         @OA\Schema(type="string", example="TSPC3JAEON")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tóm tắt hóa đơn lấy thành công",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoice summary retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="table_session_id", type="string", example="TSPC3JAEON"),
     *                 @OA\Property(
     *                     property="summary",
     *                     type="object",
     *                     @OA\Property(property="total_invoices", type="integer", example=5, description="Tổng số hóa đơn"),
     *                     @OA\Property(property="total_amount", type="number", format="float", example=1250000, description="Tổng tiền tất cả hóa đơn"),
     *                     @OA\Property(property="total_paid", type="number", format="float", example=850000, description="Tổng tiền đã thanh toán"),
     *                     @OA\Property(property="total_remaining", type="number", format="float", example=400000, description="Tổng tiền còn lại phải thanh toán"),
     *                     @OA\Property(property="unpaid_count", type="integer", example=1, description="Số hóa đơn chưa thanh toán (status=0)"),
     *                     @OA\Property(property="partially_paid_count", type="integer", example=2, description="Số hóa đơn thanh toán một phần (status=1)"),
     *                     @OA\Property(property="paid_count", type="integer", example=2, description="Số hóa đơn đã thanh toán đủ (status=2)"),
     *                     @OA\Property(property="cancelled_count", type="integer", example=0, description="Số hóa đơn đã hủy (status=3)"),
     *                     @OA\Property(property="merged_count", type="integer", example=0, description="Số hóa đơn đã gộp (status=4)")
     *                 ),
     *                 @OA\Property(
     *                     property="invoices",
     *                     type="array",
     *                     description="Chi tiết từng hóa đơn",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="invoice_id", type="string", example="IN001"),
     *                         @OA\Property(property="final_amount", type="number", format="float", example=250000),
     *                         @OA\Property(property="total_paid", type="number", format="float", example=250000),
     *                         @OA\Property(property="remaining_amount", type="number", format="float", example=0),
     *                         @OA\Property(property="status", type="integer", example=2, description="0=Chưa thanh toán, 1=Thanh toán một phần, 2=Đã thanh toán, 3=Đã hủy, 4=Đã gộp"),
     *                         @OA\Property(property="status_label", type="string", example="Đã thanh toán"),
     *                         @OA\Property(property="created_at", type="string", format="date-time")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Table Session không tồn tại",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Table session not found")
     *         )
     *     )
     * )
     */
    #[Get('/{tableSessionId}/invoice-summary', middleware: ['permission:table-sessions.view'])]
    public function getInvoiceSummary(string $tableSessionId): JsonResponse
    {
        // Kiểm tra table session có tồn tại không
        $tableSession = TableSession::find($tableSessionId);
        if (!$tableSession) {
            return $this->errorResponse('Table session not found', 404);
        }

        // Lấy tất cả invoices của table session kèm payments
        $invoices = Invoice::where('table_session_id', $tableSessionId)
            ->with(['payments' => function ($query) {
                $query->where('status', \App\Models\Payment::STATUS_COMPLETED);
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        // Khởi tạo các biến tổng hợp
        $totalInvoices = $invoices->count();
        $totalAmount = 0;
        $totalPaid = 0;
        $totalRemaining = 0;

        $unpaidCount = 0;
        $partiallyPaidCount = 0;
        $paidCount = 0;
        $cancelledCount = 0;
        $mergedCount = 0;

        // Chi tiết từng invoice
        $invoiceDetails = [];

        foreach ($invoices as $invoice) {
            // Tính tổng đã thanh toán cho invoice này
            $invoicePaid = $invoice->payments->sum('amount');
            $invoiceRemaining = max(0, $invoice->final_amount - $invoicePaid);

            $totalAmount += $invoice->final_amount;
            $totalPaid += $invoicePaid;
            $totalRemaining += $invoiceRemaining;

            // Đếm theo status
            switch ($invoice->status) {
                case Invoice::STATUS_UNPAID:
                    $unpaidCount++;
                    break;
                case Invoice::STATUS_PARTIALLY_PAID:
                    $partiallyPaidCount++;
                    break;
                case Invoice::STATUS_PAID:
                    $paidCount++;
                    break;
                case Invoice::STATUS_CANCELLED:
                    $cancelledCount++;
                    break;
                case Invoice::STATUS_MERGED:
                    $mergedCount++;
                    break;
            }

            // Thêm vào chi tiết
            $invoiceDetails[] = [
                'invoice_id' => $invoice->id,
                'final_amount' => (float) $invoice->final_amount,
                'total_paid' => (float) $invoicePaid,
                'remaining_amount' => (float) $invoiceRemaining,
                'status' => $invoice->status,
                'status_label' => $invoice->status_label,
                'operation_type' => $invoice->operation_type,
                'created_at' => $invoice->created_at->toISOString(),
            ];
        }

        $result = [
            'table_session_id' => $tableSessionId,
            'summary' => [
                'total_invoices' => $totalInvoices,
                'total_amount' => round($totalAmount, 2),
                'total_paid' => round($totalPaid, 2),
                'total_remaining' => round($totalRemaining, 2),
                'unpaid_count' => $unpaidCount,
                'partially_paid_count' => $partiallyPaidCount,
                'paid_count' => $paidCount,
                'cancelled_count' => $cancelledCount,
                'merged_count' => $mergedCount,
            ],
            'invoices' => $invoiceDetails,
        ];

        return $this->successResponse(
            $result,
            'Invoice summary retrieved successfully'
        );
    }
}
