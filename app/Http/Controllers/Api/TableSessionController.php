<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TableSession\MergeTablesRequest;
use App\Http\Requests\TableSession\SplitInvoiceRequest;
use App\Http\Requests\TableSession\SplitTableRequest;
use App\Http\Requests\TableSession\TableSessionQueryRequest;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\TableSession;
use App\Models\TableSessionDiningTable;
use App\Models\TableSessionReservation;
use App\Services\TableSessionService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
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
                    ->where('ts.status', 1)
                    ->select(
                        'tsdt.dining_table_id',
                        'ts.id',
                        'ts.type',
                        'ts.status',
                        'ts.started_at',
                        'ts.ended_at'
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
                'ts.ended_at'
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

        $perPage = $request->perPage();
        $paginator = $query->paginate($perPage);

        return $this->successResponse($paginator, 'Table sessions retrieved successfully');
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

        $session->status = 1; // Đang phục vụ
        $session->started_at = now();
        $session->save();

        return response()->json([
            'id' => $session->id,
            'status' => $session->status,
            'message' => 'Table session status updated to active'
        ], 200);
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

        $diningTableId = $data['dining_table_id'];

        // 🧩 1. Tạo session mới
        $session = TableSession::create([
            'type' => 0, // Offline
            'status' => 0, // Pending
            'parent_session_id' => null,
            'merged_into_session_id' => null,
            'started_at' => null,
            'ended_at' => null,
            'customer_id' => 'CUOJO15Z5I', // khách mặc định
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

        // 🔴 Lấy danh sách bàn đang bận (trong Pending hoặc Active)
        $busyTables = DB::table('table_session_dining_table as tsdt')
            ->join('table_sessions as ts', 'tsdt.table_session_id', '=', 'ts.id')
            ->leftJoin('table_session_reservations as tsr', 'ts.id', '=', 'tsr.table_session_id')
            ->leftJoin('reservations as r', 'r.id', '=', 'tsr.reservation_id')
            ->whereIn('ts.status', [0, 1]) // chỉ Pending & Active mới coi là bận
            ->where(function ($q) use ($reservedAt) {
                $q->where(function ($q2) use ($reservedAt) {
                    // nếu chưa kết thúc: bận trong 2 tiếng kể từ thời gian đặt bàn (reservation hoặc fallback started_at)
                    // sử dụng < để exclusive thời điểm kết thúc
                    $q2->whereNull('ts.ended_at')
                        ->whereRaw(
                            '? >= COALESCE(r.reserved_at, ts.started_at)
                        AND ? < DATE_ADD(COALESCE(r.reserved_at, ts.started_at), INTERVAL 2 HOUR)',
                            [$reservedAt, $reservedAt]
                        );
                })
                    ->orWhere(function ($q2) use ($reservedAt) {
                        // nếu đã có ended_at: bận trong khoảng từ reserved_at (hoặc started_at) đến ended_at (inclusive)
                        $q2->whereNotNull('ts.ended_at')
                            ->whereRaw(
                                '? BETWEEN COALESCE(r.reserved_at, ts.started_at) AND ts.ended_at',
                                [$reservedAt]
                            );
                    });
            })
            ->distinct()
            ->pluck('tsdt.dining_table_id')
            ->toArray();

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
     *                     @OA\Property(property="order_status", type="integer", example=1, description="0=Open, 1=In-Progress, 2=Served, 3=Paid, 4=Cancelled"),
     *                     @OA\Property(property="total_amount", type="number", format="float", example=350000),
     *                     @OA\Property(
     *                         property="items",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="order_item_id", type="string", example="OI001"),
     *                             @OA\Property(property="quantity", type="integer", example=2),
     *                             @OA\Property(property="item_price", type="number", format="float", example=120000),
     *                             @OA\Property(property="total_price", type="number", format="float", example=240000),
     *                             @OA\Property(property="item_status", type="integer", example=1, description="0=Ordered, 1=Cooking, 2=Served, 3=Cancelled"),
     *                             @OA\Property(property="notes", type="string", example="Less spicy"),
     *                             @OA\Property(property="prepared_by", type="string", example="EMP001"),
     *                             @OA\Property(property="served_at", type="string", format="date-time", example="2025-10-02T08:45:00Z"),
     *                             @OA\Property(property="cancelled_reason", type="string", example=null),
     *                             @OA\Property(
     *                                 property="dish",
     *                                 type="object",
     *                                 @OA\Property(property="dish_id", type="string", example="D123"),
     *                                 @OA\Property(property="dish_name", type="string", example="Fried Rice"),
     *                                 @OA\Property(property="dish_price", type="number", example=120000),
     *                                 @OA\Property(property="dish_desc", type="string", example="Delicious fried rice"),
     *                                 @OA\Property(property="cooking_time", type="integer", example=15),
     *                                 @OA\Property(property="image", type="string", example="/images/fried_rice.png"),
     *                                 @OA\Property(property="dish_active", type="boolean", example=true),
     *                                 @OA\Property(property="category_name", type="string", example="Main Course"),
     *                                 @OA\Property(property="category_desc", type="string", example="Main course dishes")
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
    #[Get('/{idDiningTable}/orders', middleware: ['permission:table-sessions.view'])]
    public function getOrdersBySession(string $idDiningTable): JsonResponse
    {
        $orders = DB::table('orders as o')
            ->join('order_items as oi', 'o.id', '=', 'oi.order_id')
            ->join('dishes as d', 'oi.dish_id', '=', 'd.id')
            ->join('dish_categories as dc', 'd.category_id', '=', 'dc.id')
            ->where('o.table_session_id', $idDiningTable)
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
            ->get();

        if ($orders->isEmpty()) {
            return $this->errorResponse(
                'Không tìm thấy orders cho Table Session: ' . $idDiningTable,
                404
            );
        }

        // Nhóm order_items theo order_id để format data
        $grouped = $orders->groupBy('order_id')->map(function ($rows) {
            $order = $rows->first();
            return [
                'order_id' => $order->order_id,
                'table_session_id' => $order->table_session_id,
                'order_status' => $order->order_status,
                'total_amount' => $order->total_amount,
                'items' => $rows->map(function ($r) {
                    return [
                        'order_item_id' => $r->order_item_id,
                        'quantity' => $r->quantity,
                        'item_price' => $r->item_price,
                        'total_price' => $r->total_price,
                        'item_status' => $r->item_status,
                        'notes' => $r->notes,
                        'prepared_by' => $r->prepared_by,
                        'served_at' => $r->served_at,
                        'cancelled_reason' => $r->cancelled_reason,
                        'dish' => [
                            'dish_id' => $r->dish_id,
                            'dish_name' => $r->dish_name,
                            'dish_price' => $r->dish_price,
                            'dish_desc' => $r->dish_desc,
                            'cooking_time' => $r->cooking_time,
                            'image' => $r->image,
                            'dish_active' => (bool) $r->dish_active,
                            'category_name' => $r->category_name,
                            'category_desc' => $r->category_desc,
                        ]
                    ];
                })
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
    #[Post('/merge', middleware: ['permission:table-sessions.merge'])]
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
     *     summary="Tách hóa đơn thành nhiều hóa đơn con",
     *     description="Chia một invoice thành nhiều invoice con theo order items được chọn",
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
     *                 description="Danh sách các phần tách",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(
     *                         property="order_item_ids",
     *                         type="array",
     *                         description="Danh sách ID các order items",
     *                         @OA\Items(type="string", example="OI001")
     *                     ),
     *                     @OA\Property(
     *                         property="note",
     *                         type="string",
     *                         description="Ghi chú cho phần tách",
     *                         example="Khách A"
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
     *                 @OA\Property(property="parent_invoice", type="object"),
     *                 @OA\Property(
     *                     property="child_invoices",
     *                     type="array",
     *                     @OA\Items(type="object")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation failed hoặc không thể tách",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Invoice không tồn tại",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string")
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
}
