<?php

namespace App\Http\Controllers\Api;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Put;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Prefix;

#[Prefix('auth/order-items')]
#[Middleware('auth:api')]
class OrderItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * @OA\Put(
     *     path="/api/auth/order-items/status",
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
    #[Put('/status', middleware: ['permission:orders.edit'])]
    public function updateMultipleItemStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => 'required|array', // object key-value: ['OI123' => 2, 'OI124' => 1]
        ]);

        $items = $data['items'];
        $updatedItems = [];
        $orderId = null;

        foreach ($items as $orderItemId => $status) {
            if (!in_array($status, [0, 1, 2, 3])) continue; // validate status

            $orderItem = OrderItem::find($orderItemId);
            if ($orderItem) {
                $orderItem->status = $status;
                $orderItem->save();
                $updatedItems[] = $orderItem;

                if (!$orderId) {
                    $orderId = $orderItem->order_id;
                }
            }
        }

        // Nếu có orderId, cập nhật trạng thái order dựa trên các orderItems
        if ($orderId) {
            /** @var Order $order */
            $order = Order::with('items')->find($orderId);

            if ($order) {
                $statuses = $order->items->pluck('status')->unique()->sort()->values()->all();

                $orderStatus = null;

                if ($statuses === [3]) {
                    $orderStatus = 4; // Cancelled
                } elseif (collect($statuses)->every(fn($s) => in_array($s, [2, 3]))) {
                    $orderStatus = 2; // Served
                } elseif (in_array(1, $statuses)) {
                    $orderStatus = 1; // In-Progress
                } elseif ($statuses === [0]) {
                    $orderStatus = 0; // Open
                } else {
                    $orderStatus = 1; // Mixed Ordered + Served
                }

                $order->status = $orderStatus;
                $order->save();
            }
        }

        return $this->successResponse(
            $updatedItems,
            'Order items and order status updated successfully'
        );
    }
}
