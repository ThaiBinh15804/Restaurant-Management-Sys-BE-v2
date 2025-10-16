<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\InvoiceQueryRequest;
use App\Models\Invoice;
use App\Models\InvoicePromotion;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TableSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Put;

#[Prefix('invoices')]
class InvoicePaymentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/invoices",
     *     tags={"Invoices"},
     *     summary="List invoices",
     *     description="Retrieve a paginated list of invoices with optional filters such as table session, status, and total amount range.",
     *     operationId="getInvoices",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of items per page (default 15)",
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Parameter(
     *         name="table_session_id",
     *         in="query",
     *         required=false,
     *         description="Filter by table session ID",
     *         @OA\Schema(type="string", example="TS001")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter by invoice status (e.g. pending, active, completed, cancel)",
     *         @OA\Schema(type="string", example="completed")
     *     ),
     *     @OA\Parameter(
     *         name="total_amount_min",
     *         in="query",
     *         required=false,
     *         description="Filter invoices with total amount greater than or equal to this value",
     *         @OA\Schema(type="number", format="float", example=100000)
     *     ),
     *     @OA\Parameter(
     *         name="total_amount_max",
     *         in="query",
     *         required=false,
     *         description="Filter invoices with total amount less than or equal to this value",
     *         @OA\Schema(type="number", format="float", example=1000000)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Invoices retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoices retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="string", example="INV001"),
     *                         @OA\Property(property="table_session_id", type="string", example="TS001"),
     *                         @OA\Property(property="status", type="string", example="completed"),
     *                         @OA\Property(property="total_amount", type="number", format="float", example=250000),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-10-13T12:00:00Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-10-13T13:00:00Z")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    #[Get('/', middleware: ['permission:table-sessions.view'])]
    public function index(InvoiceQueryRequest $request)
    {
        $filters = $request->filters();
        $query = Invoice::query()->orderBy("created_at", "desc");

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
     * @OA\Get(
     *     path="/api/invoices/{id}",
     *     tags={"Invoices"},
     *     summary="Get invoice detail with payments",
     *     description="Retrieve an invoice by its ID along with all related payments.",
     *     operationId="getInvoiceDetail",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Invoice ID",
     *         @OA\Schema(type="string", example="INV001")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Invoice detail retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoice detail retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="string", example="INV001"),
     *                 @OA\Property(property="table_session_id", type="string", example="TS001"),
     *                 @OA\Property(property="status", type="integer", example=1),
     *                 @OA\Property(property="total_amount", type="number", format="float", example=250000),
     *                 @OA\Property(property="discount", type="number", format="float", example=50000),
     *                 @OA\Property(property="tax", type="number", format="float", example=10),
     *                 @OA\Property(property="final_amount", type="number", format="float", example=275000),
     *                 @OA\Property(
     *                     property="payments",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="string", example="PM001"),
     *                         @OA\Property(property="amount", type="number", format="float", example=100000),
     *                         @OA\Property(property="method", type="integer", example=1),
     *                         @OA\Property(property="method_label", type="string", example="Bank Transfer"),
     *                         @OA\Property(property="status", type="integer", example=1),
     *                         @OA\Property(property="status_label", type="string", example="Completed"),
     *                         @OA\Property(property="paid_at", type="string", format="date-time", example="2025-10-13T12:00:00Z"),
     *                         @OA\Property(property="employee", type="object",
     *                             @OA\Property(property="id", type="string", example="EMP001"),
     *                             @OA\Property(property="name", type="string", example="Nguyen Van A")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Invoice not found"
     *     )
     * )
     */
    #[Get('/{id}', middleware: ['permission:table-sessions.view'])]
    public function show(string $id)
    {
        // Lấy hóa đơn và các payment kèm nhân viên
        $invoice = Invoice::with(['payments.employee'])->find($id);

        if (!$invoice) {
            return $this->errorResponse('Invoice not found', [], 404);
        }

        return $this->successResponse($invoice, 'Invoice detail retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/invoices/table-session/{id}",
     *     tags={"Invoices"},
     *     summary="Get invoice detail by table session ID",
     *     description="Retrieve the invoice and its payments (with employee info) for a given table session.",
     *     operationId="getInvoiceByTableSession",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Table session ID",
     *         @OA\Schema(type="string", example="TS001")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Invoice retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Invoice detail retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="string", example="INV001"),
     *                 @OA\Property(property="table_session_id", type="string", example="TS001"),
     *                 @OA\Property(property="total_amount", type="number", format="float", example=250000),
     *                 @OA\Property(property="discount", type="number", format="float", example=50000),
     *                 @OA\Property(property="tax", type="number", format="float", example=10),
     *                 @OA\Property(property="final_amount", type="number", format="float", example=275000),
     *                 @OA\Property(property="status", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-10-13T12:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-10-13T13:00:00Z"),
     *                 @OA\Property(
     *                     property="payments",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="string", example="PM001"),
     *                         @OA\Property(property="amount", type="number", format="float", example=150000),
     *                         @OA\Property(property="method", type="integer", example=1),
     *                         @OA\Property(property="status", type="integer", example=1),
     *                         @OA\Property(property="paid_at", type="string", format="date-time", example="2025-10-13T14:30:00Z"),
     *                         @OA\Property(property="desc_issue", type="string", example=null),
     *                         @OA\Property(
     *                             property="employee",
     *                             type="object",
     *                             @OA\Property(property="id", type="string", example="EMP001"),
     *                             @OA\Property(property="full_name", type="string", example="Nguyễn Văn A"),
     *                             @OA\Property(property="phone", type="string", example="0123456789"),
     *                             @OA\Property(property="contract_type", type="integer", example=1),
     *                             @OA\Property(property="base_salary", type="number", format="float", example=8000000),
     *                             @OA\Property(property="is_active", type="boolean", example=true),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-01T08:00:00Z")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Invoice not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invoice for this table session not found")
     *         )
     *     )
     * )
     */
    #[Get('/table-session/{id}', middleware: ['permission:table-sessions.view'])]
    public function showByTableSession(string $id)
    {
        // Lấy hóa đơn theo table_session_id kèm theo payments và nhân viên
        $invoice = Invoice::with(['payments.employee'])
            ->where('table_session_id', $id)
            ->first();

        if (!$invoice) {
            return $this->errorResponse('Invoice for this table session not found', [], 404);
        }

        return $this->successResponse($invoice, 'Invoice detail retrieved successfully');
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
            'paymentBefore' => 'nullable|numeric|min:0',
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

            $paymentAmount = $request->paymentBefore ?? $request->final_amount;
            $payment = Payment::create([
                'amount' => $paymentAmount,
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

            // Cập nhật table session: status = 2 (completed) + ended_at = now
            if ($request->paymentBefore) {
                $tableSession->status = 0; // Chờ thanh toán tiếp
                // Không set ended_at
            } else {
                $tableSession->status = 2; // Hoàn thành
                $tableSession->ended_at = now();

                // 🔹 Update tất cả orders thuộc table session => Đã trả (status = 3)
                Order::where('table_session_id', $request->table_session_id)
                    ->update(['status' => 3]);
            }
            $tableSession->save();

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

    /**
     * @OA\Put(
     *     path="/api/invoices/{invoice_id}",
     *     tags={"Invoices"},
     *     summary="Pay remaining amount of an invoice",
     *     description="Cập nhật hóa đơn đã từng thanh toán một phần, tạo payment cho phần còn lại",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="invoice_id",
     *         in="path",
     *         required=true,
     *         description="ID của hóa đơn cần thanh toán",
     *         @OA\Schema(type="string", example="INV001")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="amount", type="number", format="float", example=50000),
     *             @OA\Property(property="method", type="integer", enum={0,1}, example=0, description="0=Cash, 1=Bank transfer"),
     *             @OA\Property(property="status_payment", type="integer", enum={0,1,2,3}, example=1),
     *             @OA\Property(property="employee_id", type="string", example="EMP001")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="payment", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invoice not found or invalid payload",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invoice không tồn tại!")
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
    #[Put('/{invoice_id}', middleware: ['permission:table-sessions.view'])]
    public function payRemainingInvoice(Request $request, string $invoice_id)
    {
        $request->validate([
            'table_session_id' => 'required|string|exists:table_sessions,id',
            'total_amount' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'final_amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|integer|in:0,1,2,3',
            'listPromotionApply' => 'nullable|array',
            'listPromotionApply.*.promotion_id' => 'required|string|exists:promotions,id',
            'listPromotionApply.*.discount_value' => 'required|numeric',
            'employee_id' => 'required|string|exists:employees,id',
            'method' => 'required|integer|in:0,1',
            'status_payment' => 'required|integer|in:0,1,2,3',
            'paymentBefore' => 'nullable|numeric|min:0',
        ]);

        $invoice = Invoice::with('payments', 'tableSession')->find($invoice_id);
        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice không tồn tại!'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // ----------------------------
            // 1. Cập nhật hóa đơn nếu gửi dữ liệu
            // ----------------------------
            if ($request->filled('total_amount')) {
                $invoice->total_amount = $request->total_amount;
            }
            if ($request->filled('discount')) {
                $invoice->discount = $request->discount;
            }
            if ($request->filled('tax')) {
                $invoice->tax = $request->tax;
            }
            if ($request->filled('final_amount')) {
                $invoice->final_amount = $request->final_amount;
            }
            if ($request->filled('status')) {
                $invoice->status = $request->status;
            }
            $invoice->save();

            // ----------------------------
            // 2. Tạo payment mới
            // ----------------------------
            $paymentAmount = $request->amount ?? $request->paymentBefore ?? $request->final_amount ?? $invoice->final_amount;
            $payment = Payment::create([
                'amount' => $paymentAmount,
                'method' => $request->method,
                'status' => $request->status_payment,
                'paid_at' => now(),
                'invoice_id' => $invoice->id,
                'employee_id' => $request->employee_id,
            ]);

            // ----------------------------
            // 3. Xóa và tạo lại promotions nếu có
            // ----------------------------
            if ($request->has('listPromotionApply')) {
                InvoicePromotion::where('invoice_id', $invoice->id)->delete();
                foreach ($request->listPromotionApply as $p) {
                    InvoicePromotion::create([
                        'applied_at' => now(),
                        'discount_value' => $p['discount_value'],
                        'promotion_id' => $p['promotion_id'],
                        'invoice_id' => $invoice->id
                    ]);
                }
            }

            // ----------------------------
            // 4. Cập nhật TableSession và các bàn gộp
            // ----------------------------
            if ($invoice->tableSession) {
                $tableSession = $invoice->tableSession;

                if ($request->paymentBefore) {
                    $tableSession->status = 0; // Chờ thanh toán tiếp
                } else {
                    $tableSession->status = 2; // Hoàn thành
                    $tableSession->ended_at = now();
                }
                $tableSession->save();

                // Update các session gộp
                TableSession::where('merged_into_session_id', $tableSession->id)
                    ->update([
                        'status' => 2,
                        'ended_at' => now()
                    ]);
            }

            // ----------------------------
            // 5. Cập nhật tất cả orders về status = 3 (đã trả)
            // ----------------------------
            Order::where('table_session_id', $request->table_session_id)
                ->where('status', '!=', 4) // bỏ qua các order đã hủy
                ->update(['status' => 3]);

            DB::commit();
            return response()->json([
                'success' => true,
                'payment' => $payment,
                'invoice' => $invoice
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
