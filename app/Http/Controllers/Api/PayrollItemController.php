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
#[Middleware('auth:api')]
class PayrollItemController extends Controller
{
    #[Get('/', middleware: 'permission:payroll_items.view')]
    public function index(PayrollItemQueryRequest $request): JsonResponse
    {
        $query = PayrollItem::query()->with('payroll');
        $filters = $request->filters();

        if (!empty($filters['payroll_id'])) {
            $query->where('payroll_id', $filters['payroll_id']);
        }

        if (array_key_exists('item_type', $filters) && $filters['item_type'] !== null) {
            $query->where('item_type', $filters['item_type']);
        }

        if (!empty($filters['code'])) {
            $query->where('code', 'like', '%' . $filters['code'] . '%');
        }

        $paginator = $query->paginate($request->perPage(), ['*'], 'page', $request->page());
        $paginator->withQueryString();

        return $this->successResponse([
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Payroll items retrieved successfully');
    }

    #[Post('/', middleware: 'permission:payroll_items.create')]
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

    #[Get('/{id}', middleware: 'permission:payroll_items.view')]
    public function show(string $id): JsonResponse
    {
        $item = PayrollItem::with('payroll')->find($id);

        if (!$item) {
            return $this->errorResponse('Payroll item not found', [], 404);
        }

        return $this->successResponse($item, 'Payroll item retrieved successfully');
    }

    #[Put('/{id}', middleware: 'permission:payroll_items.edit')]
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

    #[Delete('/{id}', middleware: 'permission:payroll_items.delete')]
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
