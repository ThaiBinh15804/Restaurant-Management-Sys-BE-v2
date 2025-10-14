<?php

namespace App\Http\Controllers\Api;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
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

    public function destroy(string $id)
    {
        //
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
            'items.*.status' => 'nullable|integer|in:0,1,2,3',
            'items.*.quantity' => 'nullable|numeric|min:1',
            'items.*.notes' => 'nullable|string', // 🆕 thêm validate ghi chú
        ]);

        $items = $data['items'];
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
                if ($orderItem->status === 2 && $status === 3) {
                    $errors[$orderItemId] = "Món {$orderItem->dish_id} đã phục vụ, không thể hủy.";
                    continue;
                }

                if ($orderItem->status === 3 && $status !== 3) {
                    $errors[$orderItemId] = "Món {$orderItem->dish_id} đã hủy, không thể thay đổi trạng thái.";
                    continue;
                }

                $orderItem->status = $status;

                // Ghi thời điểm Served
                if ($status == 2 && !$orderItem->served_at) {
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

        // Cập nhật Order nếu có
        if ($orderId) {
            $order = Order::with('items')->find($orderId);

            if ($order) {
                $statuses = $order->items->pluck('status')->unique()->sort()->values()->all();

                $orderStatus = match (true) {
                    $statuses === [3] => 4, // Cancelled
                    collect($statuses)->every(fn($s) => in_array($s, [2, 3])) => 2, // Served
                    in_array(1, $statuses) => 1, // In-progress
                    $statuses === [0] => 0, // Open
                    default => 1, // Mixed
                };

                $order->status = $orderStatus;
                // Chỉ cộng tổng tiền các món chưa bị hủy
                $order->total_amount = $order->items
                    ->where('status', '!=', 3)
                    ->sum('total_price');
                $order->save();
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
     *     tags={"Orders"},
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

        // Lấy danh sách món hiện có
        $existingItems = $order->items->keyBy('dish_id');
        $createdOrUpdatedItems = [];

        foreach ($newItems as $itemData) {
            $dishId = $itemData['dish_id'];
            $quantity = $itemData['quantity'];
            $price = $itemData['price'];
            $totalPrice = $quantity * $price;
            $notes = $itemData['notes'] ?? null; // 🆕 Lấy ghi chú nếu có

            if ($existingItems->has($dishId)) {
                // Nếu món đã tồn tại → cộng dồn
                $existingItem = $existingItems[$dishId];
                $existingItem->quantity += $quantity;
                $existingItem->total_price += $totalPrice;
                if (isset($itemData['status'])) {
                    $existingItem->status = $itemData['status'];
                }
                if (isset($itemData['notes'])) {
                    $existingItem->notes = $notes; // 🆕 cập nhật ghi chú
                }
                $existingItem->save();
                $createdOrUpdatedItems[] = $existingItem;
            } else {
                // Nếu món mới → thêm mới
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'dish_id' => $dishId,
                    'quantity' => $quantity,
                    'price' => $price,
                    'total_price' => $totalPrice,
                    'status' => $itemData['status'] ?? 0,
                    'notes' => $notes, // 🆕 Lưu ghi chú
                ]);
                $createdOrUpdatedItems[] = $orderItem;
            }
        }

        // 🧮 Cập nhật tổng tiền
        $order->total_amount = $order->items()->sum('total_price');
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
            $invoice = Invoice::find($data['invoice_id']);
            if ($invoice) {
                $totalAmount = $order->total_amount;
                $totalAfterDiscount = $totalAmount * (1 - ($invoice->discount / 100));
                $finalAmount = $totalAfterDiscount * (1 + ($invoice->tax / 100));

                $invoice->total_amount = $totalAmount;
                $invoice->final_amount = $finalAmount;
                $invoice->save();
            }
        }

        $order->load('items'); // 🟢 Refresh lại dữ liệu

        DB::commit();

        return $this->successResponse(
            [
                'order' => $order,
                'items' => $createdOrUpdatedItems,
            ],
            'Order and items have been added/updated successfully.'
        );
    }
}
