<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Promotion\PromotionQueryRequest;
use App\Models\InvoicePromotion;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Put;

#[Prefix('promotions')]
class PromotionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/promotions",
     *     tags={"Promotions"},
     *     summary="List promotions",
     *     description="Retrieve a paginated list of promotions with optional filters such as code, description, discount percent, and active status.",
     *     operationId="getPromotions",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer", example=15)),
     *     @OA\Parameter(name="code", in="query", description="Filter by promotion code (partial match)", @OA\Schema(type="string", example="SALE10")),
     *     @OA\Parameter(name="desc", in="query", description="Filter by description (partial match)", @OA\Schema(type="string", example="Giảm 10%")),
     *     @OA\Parameter(name="discount_percent", in="query", description="Filter by exact discount percent", @OA\Schema(type="number", format="float", example=10)),
     *     @OA\Parameter(name="is_active", in="query", description="Filter by active status", @OA\Schema(type="boolean", example=true)),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Promotions retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Promotions retrieved successfully"),
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
     *                         @OA\Property(property="id", type="string", example="PROMO001"),
     *                         @OA\Property(property="code", type="string", example="SALE10"),
     *                         @OA\Property(property="description", type="string", example="Giảm 10% cho đơn hàng đầu tiên"),
     *                         @OA\Property(property="discount_percent", type="number", format="float", example=10),
     *                         @OA\Property(property="usage_limit", type="integer", example=50),
     *                         @OA\Property(property="used_count", type="integer", example=5),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="start_date", type="string", format="date-time", example="2025-01-01T00:00:00Z"),
     *                         @OA\Property(property="end_date", type="string", format="date-time", example="2025-12-31T23:59:59Z")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    #[Get('/', middleware: ['permission:table-sessions.view'])]
    public function index(PromotionQueryRequest $request)
    {
        $query = Promotion::withCount(['invoicePromotions as used_count'])
            ->orderBy('created_at', 'desc');

        $filters = $request->filters();

        if (!empty($filters['code'])) {
            $query->where('code', 'like', '%' . $filters['code'] . '%');
        }

        if (!empty($filters['desc'])) {
            $query->where('description', 'like', '%' . $filters['desc'] . '%');
        }

        if (!is_null($filters['is_active'] ?? null)) {
            $query->where('is_active', $filters['is_active']);
        }

        // Thêm filter discount_percent
        if (!empty($filters['discount_percent'])) {
            // Cast sang float để chắc chắn query hợp lệ
            $discount = (float) $filters['discount_percent'];
            $query->where('discount_percent', $discount);
        }

        $perPage = $request->perPage();

        $paginator = $query->paginate(
            $perPage
        );

        return $this->successResponse($paginator, 'Promotions retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/promotions",
     *     tags={"Promotions"},
     *     summary="Create new promotion",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","discount_value","start_at","end_at"},
     *             @OA\Property(property="name", type="string", example="Promo 10%"),
     *             @OA\Property(property="discount_value", type="number", format="float", example=10.00),
     *             @OA\Property(property="start_at", type="string", format="date-time"),
     *             @OA\Property(property="end_at", type="string", format="date-time"),
     *             @OA\Property(property="status", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Promotion created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    #[Post('/', middleware: ['permission:table-sessions.create'])]
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:255|unique:promotions,code',
            'description' => 'nullable|string|max:500',
            'discount_percent' => 'required|numeric|min:0|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'usage_limit' => 'required|integer|min:1',
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $promotion = Promotion::create($request->all());

        return $this->successResponse($promotion, 'Promotion created successfully', 201);
    }

    /**
     * @OA\Put(
     *     path="/api/promotions/{id}",
     *     tags={"Promotions"},
     *     summary="Update promotion",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Updated Promo"),
     *             @OA\Property(property="discount_value", type="number", format="float", example=15.00),
     *             @OA\Property(property="start_at", type="string", format="date-time"),
     *             @OA\Property(property="end_at", type="string", format="date-time"),
     *             @OA\Property(property="status", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Promotion updated successfully"),
     *     @OA\Response(response=404, description="Promotion not found")
     * )
     */
    #[Put('/{id}', middleware: ['permission:table-sessions.edit'])]
    public function update(Request $request, string $id): JsonResponse
    {
        $promotion = Promotion::find($id);

        if (!$promotion) {
            return $this->errorResponse('Promotion not found', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|string|max:255|unique:promotions,code,' . $id . ',id',
            'description' => 'nullable|string|max:500',
            'discount_percent' => 'sometimes|numeric|min:0|max:100',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'usage_limit' => 'sometimes|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $promotion->update($request->all());

        return $this->successResponse($promotion, 'Promotion updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/promotions/{id}",
     *     tags={"Promotions"},
     *     summary="Delete promotion",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Promotion deleted successfully"),
     *     @OA\Response(response=404, description="Promotion not found")
     * )
     */
    #[Delete('/{id}', middleware: ['permission:table-sessions.delete'])]
    public function destroy(string $id): JsonResponse
    {
        $promotion = Promotion::find($id);

        if (!$promotion) {
            return $this->errorResponse('Promotion not found', [], 404);
        }

        // Kiểm tra liên kết với bảng invoice_promotions
        $linkedCount = InvoicePromotion::where('promotion_id', $id)->count();

        if ($linkedCount > 0) {
            return $this->errorResponse(
                'This promotion cannot be removed because it has already been applied to' . $linkedCount . ' invoice.',
                [],
                400
            );
        }

        $promotion->delete();

        return $this->successResponse([], 'Promotion deleted successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/promotions/all",
     *     tags={"Promotions"},
     *     summary="Get all active promotions (no pagination)",
     *     operationId="getAllActivePromotions",
     *     description="Retrieve all active promotions that have not reached their usage limit",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Active promotions retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Promotions retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="string", example="PROMO001"),
     *                     @OA\Property(property="name", type="string", example="Khuyến mãi 10%"),
     *                     @OA\Property(property="discountValue", type="number", format="float", example=10)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    #[Get('/all', middleware: ['permission:table-sessions.view'])]
    public function allPromotions(): JsonResponse
    {
        // Lấy promotion đang active và chưa vượt usage_limit
        $promotions = Promotion::withCount('invoicePromotions')
            ->where('is_active', 1)
            ->havingRaw('invoice_promotions_count < usage_limit')
            ->orderBy('created_at', 'desc')
            ->get();

        // Map sang interface Promotion
        $data = $promotions->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => $p->name ?? $p->code, // fallback code nếu không có name
                'discountValue' => $p->discount_percent ?? 0,
            ];
        });

        return $this->successResponse($data, 'Promotions retrieved successfully');
    }

    /**
     * @OA\Get(
     *   path="/api/auth/promotions/{code}",
     *   tags={"Promotions"},
     *   summary="Lấy chi tiết promotion theo code hoặc id",
     *   @OA\Parameter(name="code", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=404, description="Not found")
     * )
     */
    #[Get('/{code}', middleware: ['permission:table-sessions.view'])]
    public function show(string $code): JsonResponse
    {
        $promotion = Promotion::where('code', $code)->orWhere('id', $code)->first();

        if (!$promotion) {
            return $this->errorResponse('Promotion not found', [], 404);
        }

        return $this->successResponse($promotion, 'Promotion detail');
    }
}
