<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TableSession\TableSessionQueryRequest;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Illuminate\Support\Facades\DB;

#[Prefix('auth/table-sessions')]
class TableSessionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/auth/table-sessions",
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
     * @OA\Get(
     *     path="/api/auth/table-sessions/{idDiningTable}",
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
     *     path="/api/auth/table-sessions/{tableSessionId}/orders",
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
    #[Get('/{idDiningTable}/orders', middleware: ['permission:orders.view'])]
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
     *     path="/api/table-sessions/{idDiningTable}/session-history",
     *     summary="Lấy lịch sử phiên bàn theo Dining Table",
     *     tags={"DiningTables"},
     *     description="Trả về danh sách các phiên bàn đã hoàn thành hoặc bị hủy, kèm thông tin reservation nếu có",
     *     operationId="getTableSessionHistory",
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
     *         description="Lịch sử phiên bàn retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="session_id", type="string"),
     *                 @OA\Property(property="table_id", type="string"),
     *                 @OA\Property(property="table_number", type="string"),
     *                 @OA\Property(property="session_type", type="integer", description="0-Offline,1-Merge,2-Reservation,3-Split"),
     *                 @OA\Property(property="session_status", type="integer", description="3-Completed,4-Cancelled"),
     *                 @OA\Property(property="started_at", type="string", format="date-time"),
     *                 @OA\Property(property="ended_at", type="string", format="date-time"),
     *                 @OA\Property(property="customer_id", type="string"),
     *                 @OA\Property(property="employee_id", type="string"),
     *                 @OA\Property(
     *                     property="reservations",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="reservation_id", type="string"),
     *                         @OA\Property(property="reservation_customer_id", type="string"),
     *                         @OA\Property(property="reservation_time", type="string", format="date-time"),
     *                         @OA\Property(property="number_of_people", type="integer"),
     *                         @OA\Property(property="reservation_status", type="integer", description="0-Pending,1-Confirmed,2-Cancelled,3-Completed"),
     *                         @OA\Property(property="notes", type="string", nullable=true)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Không tìm thấy lịch sử phiên bàn",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    #[Get('/{idDiningTable}/session-history', middleware: ['permission:table-sessions.view'])]
    public function getTableSessionHistory(string $idDiningTable): JsonResponse
    {
        $sessions = DB::table('dining_tables as dt')
            ->leftJoin('table_session_dining_table as tsdt', 'tsdt.dining_table_id', '=', 'dt.id')
            ->leftJoin('table_sessions as ts', 'ts.id', '=', 'tsdt.table_session_id')
            ->leftJoin('table_session_reservations as tsr', 'tsr.table_session_id', '=', 'ts.id')
            ->leftJoin('reservations as r', 'r.id', '=', 'tsr.reservation_id')
            ->leftJoin('customers as c', 'c.id', '=', 'r.customer_id') // join thêm bảng customer
            ->where('dt.id', $idDiningTable)
            ->whereIn('ts.status', [2, 3]) // chỉ lấy Completed / Cancelled
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
                'c.address as customer_address'

            )
            ->orderBy('ts.started_at', 'desc')
            ->get();

        if ($sessions->isEmpty()) {
            return $this->errorResponse(
                'Không tìm thấy lịch sử phiên bàn cho Dining Table: ' . $idDiningTable,
                404
            );
        }

        // Group theo session_id, nhưng mỗi session chỉ có 1 reservation → trả về object
        $formatted = $sessions->map(function ($session) {
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
            'Lịch sử phiên bàn retrieved successfully'
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
            ->orderBy('ts.started_at', 'desc')
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
     *     path="/api/auth/table-sessions/{idDiningTable}/session-history/{sessionId}",
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
            ->whereIn('ts.status', [2, 3])
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
}
