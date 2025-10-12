<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Promotion\PromotionQueryRequest;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
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
     *     summary="Get all promotions",
     *     description="Retrieve all promotions with pagination",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15, maximum=100)),
     *     @OA\Response(response=200, description="Promotions retrieved successfully")
     * )
     */
    #[Get('/', middleware: ['permission:table-sessions.view'])]
    public function index(PromotionQueryRequest $request): JsonResponse
    {
        $query = Promotion::orderBy('created_at', 'desc');

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
     * @OA\Get(
     *     path="/api/promotions/all",
     *     tags={"Promotions"},
     *     summary="Get all promotions",
     *     description="Retrieve all promotions without pagination",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Promotions retrieved successfully",
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
        // Lấy toàn bộ promotion đang active, sắp xếp theo created_at giảm dần
        $promotions = Promotion::where('is_active', 1)
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

        $promotion->delete();

        return $this->successResponse([], 'Promotion deleted successfully');
    }
}
