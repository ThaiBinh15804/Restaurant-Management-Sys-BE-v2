<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\InvoiceQueryRequest;
use App\Models\Invoice;
use App\Models\InvoicePromotion;
use App\Models\Payment;
use App\Models\TableSession;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Prefix;
use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Post;
use Illuminate\Support\Facades\DB;
use Spatie\RouteAttributes\Attributes\Get;

#[Prefix('invoices')]
class InvoicePaymentController extends Controller
{

    /**
     * @OA\Get(
     *     path="/api/promotions",
     *     tags={"Promotions"},
     *     summary="Get all promotions",
     *     description="Retrieve all promotions with pagination",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15, maximum=100)),
     *     @OA\Response(response=200, description="Promotions retrieved successfully")
     * )
     */
    #[Get('/', middleware: ['permission:table-sessions.view'])]
    public function index(InvoiceQueryRequest $request)
    {
        $filters = $request->filters();
        $query = Invoice::query();

        if (!empty($filters['table_session_id'])) {
            $query->where('table_session_id', $filters['table_session_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['total_amount_min'])) {
            $query->where('total_amount', '>=', $filters['total_amount_min']);
        }

        if (!empty($filters['total_amount_max'])) {
            $query->where('total_amount', '<=', $filters['total_amount_max']);
        }

        $paginator = $query->paginate($request->perPage());

        return $this->successResponse($paginator, 'Invoices retrieved successfully');
    }


    /**
     * @OA\Post(
     *     path="/api/invoices",
     *     tags={"Invoices"},
     *     summary="Create invoice with payment",
     *     description="Tạo invoice kèm payment và áp dụng các khuyến mãi nếu có",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="table_session_id", type="string", example="TS123"),
     *             @OA\Property(property="total_amount", type="number", format="float", example=100000),
     *             @OA\Property(property="discount", type="number", format="float", example=10),
     *             @OA\Property(property="tax", type="number", format="float", example=10),
     *             @OA\Property(property="final_amount", type="number", format="float", example=99000),
     *             @OA\Property(property="status", type="integer", enum={0,1,2,3}, example=2),
     *             @OA\Property(
     *                 property="listPromotionApply",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="promotion_id", type="string", example="PROMO1"),
     *                     @OA\Property(property="discount_value", type="number", format="float", example=10)
     *                 )
     *             ),
     *             @OA\Property(property="employee_id", type="string", example="EMP001"),
     *             @OA\Property(property="method", type="integer", enum={0,1}, example=0, description="0=Cash, 1=Bank transfer"),
     *             @OA\Property(property="status_payment", type="integer", enum={0,1,2,3}, example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Invoice and payment created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="invoice", type="object"),
     *             @OA\Property(property="payment", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Table session not found or invalid payload",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Table session không tồn tại!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    #[Post('/', middleware: ['permission:table-sessions.view'])]
    public function createInvoiceWithPayment(Request $request)
    {
        $request->validate([
            'table_session_id' => 'required|string',
            'total_amount' => 'required|numeric|min:0',
            'discount' => 'required|numeric|min:0',
            'tax' => 'required|numeric|min:0',
            'final_amount' => 'required|numeric|min:0',
            'status' => 'required|integer|in:0,1,2,3',
            'listPromotionApply' => 'nullable|array',
            'listPromotionApply.*.promotion_id' => 'required|string|exists:promotions,id',
            'listPromotionApply.*.discount_value' => 'required|numeric',
            'employee_id' => 'required|string|exists:employees,id',
            'method' => 'required|integer|in:0,1',
            'status_payment' => 'required|integer|in:0,1,2,3',
        ]);

        // 1. Check table session exists
        $tableSession = TableSession::find($request->table_session_id);
        if (!$tableSession) {
            return response()->json([
                'success' => false,
                'message' => 'Table session không tồn tại!'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $invoice = Invoice::create([
                'table_session_id' => $request->table_session_id,
                'total_amount' => $request->total_amount,
                'discount' => $request->discount,
                'tax' => $request->tax,
                'final_amount' => $request->final_amount,
                'status' => $request->status
            ]);

            $payment = Payment::create([
                'amount' => $request->final_amount,
                'method' => $request->method,
                'status' => $request->status_payment,
                'paid_at' => now(),
                'invoice_id' => $invoice->id,
                'employee_id' => $request->employee_id,
            ]);

            if (!empty($request->listPromotionApply)) {
                foreach ($request->listPromotionApply as $p) {
                    InvoicePromotion::create([
                        'applied_at' => now(),
                        'discount_value' => $p['discount_value'],
                        'promotion_id' => $p['promotion_id'],
                        'invoice_id' => $invoice->id
                    ]);
                }
            }

            TableSession::where('id', $request->table_session_id)
                ->update(['status' => 2]);

            DB::commit();
            return response()->json([
                'success' => true,
                'invoice' => $invoice,
                'payment' => $payment,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
