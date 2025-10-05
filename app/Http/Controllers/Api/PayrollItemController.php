<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayrollItem\PayrollItemQueryRequest;
use App\Http\Requests\PayrollItem\PayrollItemStoreRequest;
use App\Http\Requests\PayrollItem\PayrollItemUpdateRequest;
use App\Models\Payroll;
use App\Models\PayrollItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Put;

/**
 * @OA\Tag(
 *     name="Payroll Items",
 *     description="API Endpoints for Payroll Item Management"
 * )
 */
#[Prefix('payroll-items')]
class PayrollItemController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/payroll-items",
     *     tags={"Payroll Items"},
     *     summary="List payroll items",
     *     description="Retrieve a paginated list of payroll items with optional filters",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer", default=15, maximum=100)),
     *     @OA\Parameter(name="payroll_id", in="query", description="Filter by payroll ID", @OA\Schema(type="string")),
     *     @OA\Parameter(name="item_type", in="query", description="Filter by item type (0=earning, 1=deduction)", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="code", in="query", description="Filter by item code", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Payroll items retrieved successfully")
     * )
     */
    #[Get('/', middleware: 'permission:payroll_items.view')]
    public function index(PayrollItemQueryRequest $request): JsonResponse
    {
        $filters = $request->filters();
        
        $query = PayrollItem::query()->with('payroll');
        if (!empty($filters['payroll_id'])) {
            $query->where('payroll_id', $filters['payroll_id']);
        }

        if (array_key_exists('item_type', $filters) && $filters['item_type'] !== null) {
            $query->where('item_type', $filters['item_type']);
        }

        if (!empty($filters['code'])) {
            $query->where('code', 'like', '%' . $filters['code'] . '%');
        }

        $perpage = $request->perPage();
        $paginator = $query->orderBy('created_at', 'desc')->paginate($perpage);


        return $this->successResponse($paginator, 'Payroll items retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/payroll-items",
     *     tags={"Payroll Items"},
     *     summary="Create payroll item",
     *     description="Add an earning or deduction item to a payroll",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"payroll_id","item_type","code","amount"},
     *             @OA\Property(property="payroll_id", type="string", example="PAY001"),
     *             @OA\Property(property="item_type", type="integer", example=0, description="0=earning, 1=deduction"),
     *             @OA\Property(property="code", type="string", example="BONUS"),
     *             @OA\Property(property="description", type="string", example="Performance bonus"),
     *             @OA\Property(property="amount", type="number", format="float", example=150.50)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Payroll item created successfully"),
     *     @OA\Response(response=404, description="Payroll not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    #[Post('/', middleware: ['permission:payroll_items.create', 'auth:api'])]
    public function store(PayrollItemStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $payroll = Payroll::find($data['payroll_id']);

        if (!$payroll) {
            return $this->errorResponse('Payroll not found', [], 404);
        }

        $item = null;

        DB::transaction(function () use ($data, $payroll, &$item) {
            $item = PayrollItem::create($data);
            $this->recalculatePayroll($payroll);
        });

        $item?->refresh();

        return $this->successResponse($item?->load('payroll'), 'Payroll item created successfully', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/payroll-items/{id}",
     *     tags={"Payroll Items"},
     *     summary="Show payroll item",
     *     description="Retrieve details for a specific payroll item",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Payroll item ID", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Payroll item retrieved successfully"),
     *     @OA\Response(response=404, description="Payroll item not found")
     * )
     */
    #[Get('/{id}', middleware: ['permission:payroll_items.view', 'auth:api'])]
    public function show(string $id): JsonResponse
    {
        $item = PayrollItem::with('payroll')->find($id);

        if (!$item) {
            return $this->errorResponse('Payroll item not found', [], 404);
        }

        return $this->successResponse($item, 'Payroll item retrieved successfully');
    }

    /**
     * @OA\Put(
     *     path="/api/payroll-items/{id}",
     *     tags={"Payroll Items"},
     *     summary="Update payroll item",
     *     description="Update amount, code, or description for a payroll item",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Payroll item ID", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="item_type", type="integer", example=1),
     *             @OA\Property(property="code", type="string", example="MEAL_DED"),
     *             @OA\Property(property="description", type="string", example="Meal plan deduction"),
     *             @OA\Property(property="amount", type="number", format="float", example=25.00)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Payroll item updated successfully"),
     *     @OA\Response(response=404, description="Payroll item not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    #[Put('/{id}', middleware: ['permission:payroll_items.edit', 'auth:api'])]
    public function update(PayrollItemUpdateRequest $request, string $id): JsonResponse
    {
        $item = PayrollItem::find($id);

        if (!$item) {
            return $this->errorResponse('Payroll item not found', [], 404);
        }

        $data = $request->validated();
        $payroll = $item->payroll;

        DB::transaction(function () use ($item, $data, $payroll) {
            $item->update($data);
            $this->recalculatePayroll($payroll);
        });

        $item->refresh();

        return $this->successResponse($item->load('payroll'), 'Payroll item updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/payroll-items/{id}",
     *     tags={"Payroll Items"},
     *     summary="Delete payroll item",
     *     description="Remove a payroll item and recalculate the payroll total",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Payroll item ID", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Payroll item deleted successfully"),
     *     @OA\Response(response=404, description="Payroll item not found")
     * )
     */
    #[Delete('/{id}', middleware: ['permission:payroll_items.delete', 'auth:api'])]
    public function destroy(string $id): JsonResponse
    {
        $item = PayrollItem::find($id);

        if (!$item) {
            return $this->errorResponse('Payroll item not found', [], 404);
        }

        $payroll = $item->payroll;

        DB::transaction(function () use ($item, $payroll) {
            $item->delete();
            $this->recalculatePayroll($payroll);
        });

        return $this->successResponse([], 'Payroll item deleted successfully');
    }

    private function recalculatePayroll(Payroll $payroll): void
    {
        $totals = $payroll->items()
            ->selectRaw('sum(case when item_type = ? then amount else 0 end) as earnings, sum(case when item_type = ? then amount else 0 end) as deductions', [
                PayrollItem::TYPE_EARNING,
                PayrollItem::TYPE_DEDUCTION,
            ])->first();

        $earnings = (float) ($totals->earnings ?? 0);
        $deductions = (float) ($totals->deductions ?? 0);

        $finalSalary = $this->calculateFinalSalary(
            (float) $payroll->base_salary,
            (float) $payroll->bonus + $earnings,
            (float) $payroll->deductions + $deductions
        );

        $payroll->forceFill([
            'final_salary' => $finalSalary,
        ])->save();
    }

    private function calculateFinalSalary(float $baseSalary, float $totalEarnings, float $totalDeductions): float
    {
        return round(max(0, $baseSalary + $totalEarnings - $totalDeductions), 2);
    }
}
