<?php

namespace App\Http\Controllers\Api;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Put;
use Spatie\RouteAttributes\Attributes\Prefix;

#[Prefix('order-items')]
class OrderItemController extends Controller
{

    public function index()
    {
        //
    }

    public function store(Request $request)
    {
        //
    }

    public function show(string $id)
    {
        //
    }

    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * @OA\Delete(
     *     path="/api/order-items/{id}",
     *     summary="Xóa một món trong order",
     *     description="Xóa order item theo ID. Nếu món chưa thuộc hóa đơn thì chỉ cần xóa, không cập nhật gì thêm.",
     *     tags={"OrderItems"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID của order item cần xóa",
     *         @OA\Schema(type="string", example="123")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Xóa món thành công",
     *         @OA\JsonContent(
     *             example={"message": "Order item deleted successfully"}
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Không tìm thấy order item",
     *         @OA\JsonContent(
     *             example={"message": "Order item not found"}
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Không có quyền hoặc chưa đăng nhập"
     *     ),
     * )
     */
    #[Delete('/{id}', middleware: ['permission:orders.edit'])]
    public function destroy(string $id, Request $request)
    {
        $orderId = $request->query('order_id');
        $orderItem = OrderItem::find($id);

        if (!$orderItem) {
            return response()->json(['message' => 'Order item not found'], 404);
        }

        // Xóa món
        $orderItem->delete();

        // Nếu có order_id => cập nhật lại tổng tiền
        if ($orderId) {
            $order = Order::find($orderId);
            if ($order) {
                $newTotal = OrderItem::where('order_id', $orderId)
                    ->where('status', '!=', 4) // loại trừ món hủy
                    ->sum('total_price');
                $order->update(['total_amount' => $newTotal]);
            }
        }

        return response()->json(['message' => 'Order item deleted successfully']);
    }

    /**
     * @OA\Put(
     *     path="/api/order-items/status",
     *     summary="Cập nhật trạng thái nhiều OrderItem",
     *     tags={"OrderItems"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="items",
     *                 type="object",
     *                 description="Key-value: orderItemId => status",
     *                 additionalProperties=@OA\Property(
     *                     type="integer",
     *                     enum={0,1,2,3},
     *                     description="0=Ordered, 1=Cooking, 2=Served, 3=Cancelled"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order items and order status updated successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="string", description="OrderItem ID"),
     *                 @OA\Property(property="order_id", type="string", description="Order ID"),
     *                 @OA\Property(property="status", type="integer", enum={0,1,2,3}, description="OrderItem status"),
     *                 @OA\Property(property="quantity", type="integer"),
     *                 @OA\Property(property="price", type="number", format="float"),
     *                 @OA\Property(property="total_price", type="number", format="float"),
     *                 @OA\Property(property="notes", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validate lỗi input"),
     *     @OA\Response(response=403, description="Không có quyền")
     * )
     */
    #[Put('/update-order', middleware: ['permission:orders.edit'])]
    public function updateMultipleItemStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => 'required|array',
            'items.*.status' => 'nullable|integer|in:0,1,2,3,4',
            'items.*.quantity' => 'nullable|numeric|min:1',
            'items.*.notes' => 'nullable|string', // 🆕 thêm validate ghi chú
            'invoice_id' => 'nullable|string|exists:invoices,id'
        ]);

        $items = $data['items'];
        $invoiceId = $data['invoice_id'] ?? null;
        $updatedItems = [];
        $orderId = null;

        /** @var array<int|string, string> $errors */
        $errors = [];

        foreach ($items as $orderItemId => $itemData) {
            if (!is_array($itemData)) continue;

            $status = $itemData['status'] ?? null;
            $quantity = $itemData['quantity'] ?? null;
            $notes = $itemData['notes'] ?? null; // 🆕 lấy ghi chú

            $orderItem = OrderItem::find($orderItemId);

            if (!$orderItem) {
                $errors[$orderItemId] = "OrderItem $orderItemId không tồn tại.";
                continue;
            }

            // Kiểm tra trạng thái hợp lệ trước khi cập nhật
            if ($status !== null) {
                // Nếu món đã phục vụ (3) => không thể đổi sang đã hủy (4)
                if ($orderItem->status === 3 && $status === 4) {
                    $errors[$orderItemId] = "Món {$orderItem->dish_id} đã phục vụ, không thể hủy.";
                    continue;
                }

                // Nếu món đã hủy (4) => không thể đổi sang bất kỳ trạng thái nào khác
                if ($orderItem->status === 4 && $status !== 4) {
                    $errors[$orderItemId] = "Món {$orderItem->dish_id} đã hủy, không thể thay đổi trạng thái.";
                    continue;
                }

                // Cập nhật trạng thái nếu hợp lệ
                $orderItem->status = $status;

                // Ghi thời điểm phục vụ
                if ($status == 3 && !$orderItem->served_at) {
                    $orderItem->served_at = now();
                }
            }

            // 🆕 Cập nhật ghi chú nếu có
            if ($notes !== null) {
                $orderItem->notes = $notes;
            }

            if (is_numeric($quantity) && $quantity > 0) {
                $orderItem->quantity = $quantity;
                $orderItem->total_price = $orderItem->price * $quantity;
            }

            $orderItem->save();
            $updatedItems[] = $orderItem;

            if (!$orderId) {
                $orderId = $orderItem->order_id;
            }
        }

        $orderIds = collect($updatedItems)->pluck('order_id')->unique();

        foreach ($orderIds as $oid) {
            $order = Order::with('items')->find($oid);
            if ($order) {
                // Cập nhật total_amount chỉ tính món chưa hủy
                $order->total_amount = $order->items
                    ->where('status', '!=', 4)
                    ->sum('total_price');

                // Xác định trạng thái Order dựa trên các items
                $statuses = $order->items->pluck('status');
                $collection = collect($statuses);

                if ($collection->every(fn($s) => $s === 4)) {
                    $orderStatus = 4; // tất cả bị hủy
                } elseif ($collection->every(fn($s) => $s === 3)) {
                    $orderStatus = 2; // tất cả đã phục vụ
                } elseif ($collection->contains(3) && $collection->every(fn($s) => in_array($s, [3, 4]))) {
                    $orderStatus = 2; // có món phục vụ và phần còn lại bị hủy
                } elseif ($collection->contains(1)) {
                    $orderStatus = 1; // có món đang chế biến
                } elseif ($collection->every(fn($s) => $s === 0)) {
                    $orderStatus = 0; // tất cả mới gọi
                } else {
                    $orderStatus = 1; // pha trộn => đang chế biến
                }

                $order->status = $orderStatus;
                $order->save();
            }
        }

        $hasCancelledItem = collect($updatedItems)->contains(fn($item) => $item->status === 4);

        if ($invoiceId && $hasCancelledItem) {
            $invoice = Invoice::with('payments')->find($invoiceId);
            if ($invoice) {
                $newTotal = Order::where('table_session_id', $invoice->table_session_id)
                    ->with(['items' => function ($q) {
                        $q->where('status', '!=', 4);
                    }])
                    ->get()
                    ->flatMap->items
                    ->sum('total_price');

                $invoice->total_amount = $newTotal;
                $invoice->final_amount = ($newTotal * (1 - $invoice->discount / 100)) * (1 + $invoice->tax / 100);
                $invoice->save();

                // Xử lý hoàn tiền nếu cần
                $paid = $invoice->payments->sum('amount');
                $refund = $paid - $invoice->final_amount;
                if ($refund > 0) {
                    Payment::create([
                        'amount' => -$refund,
                        'method' => $invoice->payments->first()->method ?? 0,
                        'status' => 3,
                        'paid_at' => now(),
                        'invoice_id' => $invoice->id,
                        'employee_id' => $invoice->payments->first()->employee_id ?? null,
                    ]);
                }
            }
        }

        // Nếu có lỗi từng món, trả về mảng lỗi
        if (!empty($errors)) {
            return $this->errorResponse(
                'Một số món không thể cập nhật trạng thái', // message string
                $errors, // mảng lỗi chi tiết
                422
            );
        }

        return $this->successResponse(
            $updatedItems,
            'Order items, quantities, and order total updated successfully.'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/order-items/add-order",
     *     summary="Thêm order mới cùng danh sách món",
     *     tags={"OrderItems"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"order_id","items"},
     *             @OA\Property(
     *                 property="order_id",
     *                 type="string",
     *                 description="ID của order"
     *             ),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 description="Danh sách món ăn trong order",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"dish_id","name_dish","price","quantity"},
     *                     @OA\Property(property="dish_id", type="string", description="ID món ăn"),
     *                     @OA\Property(property="name_dish", type="string", description="Tên món ăn"),
     *                     @OA\Property(property="price", type="number", format="float", description="Đơn giá món"),
     *                     @OA\Property(property="quantity", type="integer", description="Số lượng"),
     *                     @OA\Property(property="status", type="integer", enum={0,1,2,3}, description="Trạng thái món (0=Open,1=In-progress,2=Served,3=Cancelled)", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order và các món đã được tạo thành công",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="order",
     *                     type="object",
     *                     @OA\Property(property="id", type="string"),
     *                     @OA\Property(property="status", type="integer"),
     *                     @OA\Property(property="total_amount", type="number", format="float")
     *                 ),
     *                 @OA\Property(
     *                     property="items",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="string", description="OrderItem ID"),
     *                         @OA\Property(property="dish_id", type="string"),
     *                         @OA\Property(property="name_dish", type="string"),
     *                         @OA\Property(property="price", type="number", format="float"),
     *                         @OA\Property(property="quantity", type="integer"),
     *                         @OA\Property(property="total_price", type="number", format="float"),
     *                         @OA\Property(property="status", type="integer", enum={0,1,2,3})
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Order và các món đã được tạo thành công.")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validate lỗi input"),
     *     @OA\Response(response=403, description="Không có quyền")
     * )
     */
    #[Post('/add-order', middleware: ['permission:orders.create'])]
    public function addOrder(Request $request): JsonResponse
    {
        // Validate dữ liệu
        $data = $request->validate([
            'order_id' => 'nullable|string', // có thể null nếu là order mới
            'table_session_id' => 'nullable|string|required_without:order_id',
            'invoice_id' => 'nullable|string', // id hóa đơn, nếu có
            'items' => 'required|array|min:1',
            'items.*.dish_id' => 'required|string',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.status' => 'nullable|integer|in:0,1,2,3',
            'items.*.notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        $orderId = $data['order_id'] ?? null;
        $tableSessionId = $data['table_session_id'] ?? null;
        $newItems = $data['items'];

        // 🟢 Nếu chưa có order_id → tạo mới Order
        if (!$orderId) {
            $order = Order::create([
                'table_session_id' => $tableSessionId,
                'status' => 0, // open
                'total_amount' => 0
            ]);
            $orderId = $order->id;
        } else {
            // 🟢 Nếu có order_id → lấy lại Order
            $order = Order::with('items')->firstOrCreate(
                ['id' => $orderId],
                ['status' => 0, 'total_amount' => 0]
            );
        }

        $createdItems = [];

        foreach ($newItems as $itemData) {
            $quantity = $itemData['quantity'];
            $price = $itemData['price'];
            $totalPrice = $quantity * $price;

            // ✅ Luôn tạo mới, KHÔNG kiểm tra trùng dish_id
            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'dish_id' => $itemData['dish_id'],
                'quantity' => $quantity,
                'price' => $price,
                'total_price' => $totalPrice,
                'status' => $itemData['status'] ?? 0,
                'notes' => $itemData['notes'] ?? null,
            ]);

            $createdItems[] = $orderItem;
        }

        // 🧮 Cập nhật tổng tiền
        $order->total_amount = $order->items()->where('status', '!=', 4)->sum('total_price');
        $order->save();

        // Sau khi thêm xong tất cả items

        // 🧩 Cập nhật trạng thái order
        $statuses = $order->items->pluck('status')->unique()->sort()->values()->all();

        $order->status = match (true) {
            $statuses === [3] => 4, // Cancelled
            collect($statuses)->every(fn($s) => in_array($s, [2, 3])) => 2, // Served
            in_array(1, $statuses) => 1, // In-progress
            $statuses === [0] => 0, // Open
            default => 1, // Mixed
        };

        if (!empty($data['invoice_id'])) {
            $invoice = Invoice::with('payments')->find($data['invoice_id']);
            if ($invoice) {
                // Lấy tất cả order cùng session
                $newTotal = Order::where('table_session_id', $invoice->table_session_id)
                    ->with(['items' => function ($q) {
                        $q->where('status', '!=', 4); // chỉ lấy món chưa bị hủy
                    }])
                    ->get()
                    ->flatMap->items
                    ->sum('total_price');

                $invoice->total_amount = $newTotal;

                // Nếu discount là phần trăm
                $invoice->final_amount = ($newTotal * (1 - ($invoice->discount / 100))) * (1 + ($invoice->tax / 100));

                $invoice->save();

                // Tính số còn lại phải thanh toán
                $paid = $invoice->payments->sum('amount');
                $remaining = $invoice->final_amount - $paid;
                // $remaining chính là số tiền khách cần thanh toán tiếp
            }
        }

        $order->load('items'); // 🟢 Refresh lại dữ liệu

        DB::commit();

        return $this->successResponse(
            [
                'order' => $order,
                'items' => $createdItems,
            ],
            'Order and items have been added/updated successfully.'
        );
    }
}
