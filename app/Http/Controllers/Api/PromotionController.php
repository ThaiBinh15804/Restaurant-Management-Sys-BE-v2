<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Get;
use OpenApi\Attributes as OA;

#[Prefix('promotions')]
class PromotionController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/auth/promotions",
     *   tags={"Promotions"},
     *   summary="Danh sách promotion (lọc cơ bản)",
     *   @OA\Parameter(name="q", in="query", required=false, @OA\Schema(type="string"), description="Tìm theo code hoặc mô tả"),
     *   @OA\Parameter(name="only_valid", in="query", required=false, @OA\Schema(type="boolean"), description="Chỉ lấy promotion còn hiệu lực"),
     *   @OA\Response(response=200, description="OK")
     * )
     */
    #[Get('/', middleware: ['permission:table-sessions.view'])]
    public function index(Request $request): JsonResponse
    {
        $q = Promotion::query();

        if ($request->boolean('only_valid')) {
            $q->currentlyValid();
        }

        if ($request->filled('q')) {
            $kw = $request->q;
            $q->where(function($s) use ($kw){
                $s->where('code', 'like', "%$kw%")
                  ->orWhere('description', 'like', "%$kw%");
            });
        }

        $data = $q->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return $this->successResponse($data, 'Promotions retrieved');
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