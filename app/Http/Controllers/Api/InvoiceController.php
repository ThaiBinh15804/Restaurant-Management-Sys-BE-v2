<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Prefix;

#[Prefix('invoices')]
#[Middleware('auth:api')]
class InvoiceController extends Controller
{


    /**
     * @OA\Get(
     *     path="/api/invoices/my-invoices",
     *     tags={"Customer Invoice"},
     *     summary="Get all customer invoices",
     *     description="Get all invoices history for logged-in customer",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Invoices list retrieved successfully"
     *     )
     * )
     */
    #[Get('my-invoices')]
    public function getMyInvoices(): JsonResponse
    {
        $user = Auth::user();

        // Lấy customer_id của user hiện tại
        $customer = $user->customerProfile;

        if (!$customer) {
            return $this->errorResponse(
                'Customer profile not found',
                [],
                404
            );
        }

        // Lấy tất cả invoices của customer thông qua reservations
        $invoices = Invoice::with([
            'tableSession',
        ])
            ->whereHas('tableSession', function ($query) use ($customer) {
                $query->whereHas('reservations', function ($q) use ($customer) {
                    $q->where('customer_id', $customer->id);
                });
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($invoice) {
                $tableNumber = $invoice->tableSession->diningTables->first()->table_number ?? 'N/A';
                return [
                    'invoice_id' => $invoice->id,
                    'table_session_id' => $invoice->table_session_id,
                    'table_id' => $tableNumber,
                    'total_amount' => number_format($invoice->total_amount, 2),
                    'discount_amount' => number_format($invoice->discount, 2),
                    'tax_amount' => number_format($invoice->tax, 2),
                    'final_amount' => number_format($invoice->final_amount, 2),
                    'status' => $invoice->status,
                    'status_label' => $invoice->status_label,
                    'created_at' => $invoice->created_at ? $invoice->created_at->format('Y-m-d H:i:s') : null,
                ];
            });

        return $this->successResponse(
            $invoices,
            'Invoices retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/invoices/my-invoices-with-items",
     *     tags={"Customer Invoice"},
     *     summary="Get all customer invoices with items",
     *     description="Get all invoices history for logged-in customer, including dish details",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Invoices with items retrieved successfully"
     *     )
     * )
     */
    #[Get('my-invoices-with-items')]
    public function getMyInvoicesWithItems(): JsonResponse
    {
        $user = Auth::user();
        $customer = $user->customerProfile;

        if (!$customer) {
            return $this->errorResponse(
                'Customer profile not found',
                [],
                404
            );
        }

        $invoices = Invoice::with([
            'tableSession.orders.items.dish',
            'tableSession.diningTables'
        ])
            ->whereHas('tableSession', function ($query) use ($customer) {
                $query->whereHas('reservations', function ($q) use ($customer) {
                    $q->where('customer_id', $customer->id);
                });
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($invoice) {
                $tableNumber = $invoice->tableSession->diningTables->first()->table_number ?? 'N/A';

                // Lấy tất cả items của các orders trong tableSession
                $items = collect();
                foreach ($invoice->tableSession->orders as $order) {
                    foreach ($order->items as $item) { // Sửa ở đây
                        $items->push([
                            'order_item_id' => $item->id,
                            'dish_id' => $item->dish_id,
                            'dish_name' => $item->dish->name ?? null,
                            'dish_desc' => $item->dish->desc ?? null,
                            'dish_image' => $item->dish->image ?? null,
                            'quantity' => $item->quantity,
                            'item_price' => number_format($item->price, 2),
                            'total_price' => number_format($item->total_price, 2),
                            'notes' => $item->notes,
                        ]);
                    }
                }

                return [
                    'invoice_id' => $invoice->id,
                    'table_session_id' => $invoice->table_session_id,
                    'table_id' => $tableNumber,
                    'total_amount' => number_format($invoice->total_amount, 2),
                    'discount_amount' => number_format($invoice->discount, 2),
                    'tax_amount' => number_format($invoice->tax, 2),
                    'final_amount' => number_format($invoice->final_amount, 2),
                    'status' => $invoice->status,
                    'status_label' => $invoice->status_label,
                    'created_at' => $invoice->created_at ? $invoice->created_at->format('Y-m-d H:i:s') : null,
                    'items' => $items,
                ];
            });

        return $this->successResponse(
            $invoices,
            'Invoices with items retrieved successfully'
        );
    }
}
