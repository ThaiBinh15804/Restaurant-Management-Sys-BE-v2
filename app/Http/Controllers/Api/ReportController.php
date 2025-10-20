<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\RangeReportRequest;
use App\Http\Requests\Report\RevenueReportRequest;
use App\Http\Requests\Report\TopDishReportRequest;
use App\Models\Invoice;
use App\Models\InvoicePromotion;
use App\Models\OrderItem;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Prefix;

/**
 * @OA\Tag(
 *     name="Reports",
 *     description="Analytics and reporting endpoints"
 * )
 */
#[Prefix('reports')]
class ReportController extends Controller
{

    /**
     * @OA\Get(
     *     path="/api/reports/revenue",
     *     tags={"Reports"},
     *     summary="Revenue timeline",
     *     operationId="getRevenueReport",
     *     description="Return aggregated revenue amounts grouped by day, week, or month within a date range.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="start_date", in="query", required=true, description="Start date (YYYY-MM-DD)", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=true, description="End date (YYYY-MM-DD)", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="group_by", in="query", description="Grouping type: day, week, month", @OA\Schema(type="string", enum={"day","week","month"}, default="day")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Revenue dataset retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Revenue dataset retrieved"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="range",
     *                     type="object",
     *                     @OA\Property(property="start", type="string", example="2025-10-01"),
     *                     @OA\Property(property="end", type="string", example="2025-10-31")
     *                 ),
     *                 @OA\Property(property="group_by", type="string", example="day"),
     *                 @OA\Property(property="total_amount", type="number", format="float", example=12500000.50),
     *                 @OA\Property(property="transaction_count", type="integer", example=123),
     *                 @OA\Property(
     *                     property="dataset",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="period_start", type="string", example="2025-10-01 00:00:00"),
     *                         @OA\Property(property="period_end", type="string", example="2025-10-01 23:59:59"),
     *                         @OA\Property(property="label", type="string", example="2025-10-01"),
     *                         @OA\Property(property="total_amount", type="number", example=523000.50),
     *                         @OA\Property(property="transaction_count", type="integer", example=18)
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    #[Get('revenue', middleware : ['permission:statistics.view'])]
    public function revenue(RevenueReportRequest $request): JsonResponse
    {
        [$start, $end] = $request->dateRange();
        $groupBy = $request->groupBy();

        $payments = Payment::query()
            ->where('status', Payment::STATUS_COMPLETED)
            ->whereBetween('paid_at', [$start, $end]);

        $dataset = match ($groupBy) {
            'week' => $this->aggregateRevenueByWeek(clone $payments),
            'month' => $this->aggregateRevenueByMonth(clone $payments),
            default => $this->aggregateRevenueByDay(clone $payments),
        };

        $total = clone $payments;

        return $this->successResponse([
            'range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'group_by' => $groupBy,
            'total_amount' => (float) $total->sum('amount'),
            'transaction_count' => (int) $total->count(),
            'dataset' => $dataset,
        ], 'Revenue dataset retrieved');
    }

    /**
     * @OA\Get(
     *     path="/api/reports/top-dishes",
     *     tags={"Reports"},
     *     summary="Top selling dishes",
     *     operationId="getTopDishesReport",
     *     description="Return best-selling dishes in the selected period with quantity and revenue.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="start_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=5, minimum=1, maximum=50)),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Top dishes retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Top dishes retrieved"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_gross_revenue", type="number", example=15000000),
     *                 @OA\Property(property="total_discount_considered", type="number", example=1200000),
     *                 @OA\Property(
     *                     property="dataset",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="dish_id", type="string", example="DSH001"),
     *                         @OA\Property(property="dish_name", type="string", example="Gà chiên mắm"),
     *                         @OA\Property(property="category_name", type="string", example="Món chính"),
     *                         @OA\Property(property="total_quantity", type="integer", example=120),
     *                         @OA\Property(property="gross_revenue", type="number", example=4200000),
     *                         @OA\Property(property="estimated_discount_share", type="number", example=320000),
     *                         @OA\Property(property="estimated_net_revenue", type="number", example=3880000),
     *                         @OA\Property(property="average_unit_price", type="number", example=35000)
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    #[Get('top-dishes', middleware : ['permission:statistics.view'])]
    public function topDishes(TopDishReportRequest $request): JsonResponse
    {
        [$start, $end] = $request->dateRange();
        $limit = $request->limit();

        $baseQuery = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('dishes', 'dishes.id', '=', 'order_items.dish_id')
            ->leftJoin('dish_categories', 'dish_categories.id', '=', 'dishes.category_id')
            ->where('orders.status', 3)
            ->whereBetween('orders.created_at', [$start, $end]);

        $totalGross = (clone $baseQuery)->sum(DB::raw('order_items.total_price'));

        $records = (clone $baseQuery)
            ->selectRaw('order_items.dish_id as dish_id, dishes.name as dish_name, COALESCE(dish_categories.name, "Uncategorized") as category_name, SUM(order_items.quantity) as total_quantity, SUM(order_items.total_price) as gross_revenue, AVG(order_items.price) as average_unit_price')
            ->groupBy('order_items.dish_id', 'dishes.name', 'dish_categories.name')
            ->orderByDesc('total_quantity')
            ->orderByDesc('gross_revenue')
            ->limit($limit)
            ->get();

        $discountAllocation = $this->calculateGlobalDiscountAllocation($start, $end, $totalGross);

        $dataset = $records->map(function ($row) use ($totalGross, $discountAllocation) {
            $gross = (float) $row->gross_revenue;
            $quantity = (int) $row->total_quantity;
            $discountShare = $totalGross > 0 ? $discountAllocation * ($gross / $totalGross) : 0.0;
            $net = $gross - $discountShare;

            return [
                'dish_id' => $row->dish_id,
                'dish_name' => $row->dish_name,
                'category_name' => $row->category_name,
                'total_quantity' => $quantity,
                'gross_revenue' => round($gross, 2),
                'estimated_discount_share' => round($discountShare, 2),
                'estimated_net_revenue' => round($net, 2),
                'average_unit_price' => round((float) $row->average_unit_price, 2),
            ];
        });

        return $this->successResponse([
            'range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'total_gross_revenue' => round((float) $totalGross, 2),
            'total_discount_considered' => round($this->calculateTotalDiscount($start, $end), 2),
            'dataset' => $dataset,
        ], 'Top dishes retrieved');
    }

    /**
     * @OA\Get(
     *     path="/api/reports/category-profit",
     *     tags={"Reports"},
     *     summary="Profit share by category",
     *     operationId="getCategoryProfitReport",
     *     description="Return gross revenue, estimated profit, and ratio per dish category.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Category profit dataset retrieved",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Category profit dataset retrieved"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_gross_revenue", type="number", example=18500000),
     *                 @OA\Property(property="total_estimated_profit", type="number", example=16500000),
     *                 @OA\Property(
     *                     property="dataset",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="category_id", type="string", example="CAT001"),
     *                         @OA\Property(property="category_name", type="string", example="Đồ uống"),
     *                         @OA\Property(property="gross_revenue", type="number", example=4500000),
     *                         @OA\Property(property="estimated_profit", type="number", example=4200000),
     *                         @OA\Property(property="profit_ratio_percent", type="number", example=25.3)
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    #[Get('category-profit', middleware : ['permission:statistics.view'])]
    public function categoryProfit(RangeReportRequest $request): JsonResponse
    {
        [$start, $end] = $request->dateRange();

        $baseQuery = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('dishes', 'dishes.id', '=', 'order_items.dish_id')
            ->leftJoin('dish_categories', 'dish_categories.id', '=', 'dishes.category_id')
            ->where('orders.status', 3)
            ->whereBetween('orders.created_at', [$start, $end]);

        $categoryRecords = (clone $baseQuery)
            ->selectRaw('dishes.category_id as category_id, COALESCE(dish_categories.name, "Uncategorized") as category_name, SUM(order_items.total_price) as gross_revenue, SUM(order_items.quantity) as total_quantity')
            ->groupBy('dishes.category_id', 'dish_categories.name')
            ->get();

        $totalGross = $categoryRecords->sum(fn($row) => (float) $row->gross_revenue);
        $totalDiscount = $this->calculateTotalDiscount($start, $end);

        $dataset = $categoryRecords->map(function ($row) use ($totalGross, $totalDiscount) {
            $gross = (float) $row->gross_revenue;
            $discountShare = $totalGross > 0 ? $totalDiscount * ($gross / $totalGross) : 0.0;
            $profit = $gross - $discountShare;

            return [
                'category_id' => $row->category_id,
                'category_name' => $row->category_name,
                'total_quantity' => (int) $row->total_quantity,
                'gross_revenue' => round($gross, 2),
                'allocated_discount' => round($discountShare, 2),
                'estimated_profit' => round($profit, 2),
            ];
        })->values();

        $totalProfit = $dataset->sum(fn($row) => $row['estimated_profit']);

        $dataset = $dataset->map(function ($row) use ($totalProfit) {
            $ratio = $totalProfit > 0 ? ($row['estimated_profit'] / $totalProfit) * 100 : 0.0;
            $row['profit_ratio_percent'] = round($ratio, 2);
            return $row;
        });

        return $this->successResponse([
            'range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'total_gross_revenue' => round((float) $totalGross, 2),
            'total_discount_considered' => round((float) $totalDiscount, 2),
            'total_estimated_profit' => round((float) $totalProfit, 2),
            'dataset' => $dataset,
        ], 'Category profit dataset retrieved');
    }

    /**
     * @OA\Get(
     *     path="/api/reports/payment-methods",
     *     tags={"Reports"},
     *     summary="Payment method distribution",
     *     operationId="getPaymentMethodsReport",
     *     description="Return total amount and percentage by payment method for completed payments.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Payment method distribution retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payment method distribution retrieved"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_amount", type="number", example=9500000),
     *                 @OA\Property(
     *                     property="dataset",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="method", type="integer", example=1),
     *                         @OA\Property(property="method_label", type="string", example="Cash"),
     *                         @OA\Property(property="transaction_count", type="integer", example=80),
     *                         @OA\Property(property="total_amount", type="number", example=5800000),
     *                         @OA\Property(property="percentage", type="number", example=61.05)
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    #[Get('payment-methods', middleware : ['permission:statistics.view'])]
    public function paymentMethods(RangeReportRequest $request): JsonResponse
    {
        [$start, $end] = $request->dateRange();

        $payments = Payment::query()
            ->where('status', Payment::STATUS_COMPLETED)
            ->whereBetween('paid_at', [$start, $end]);

        $totalAmount = (clone $payments)->sum('amount');

        $methodLabels = [
            Payment::METHOD_CASH => 'Cash',
            Payment::METHOD_BANK_TRANSFER => 'Bank Transfer',
        ];

        $dataset = (clone $payments)
            ->selectRaw('method, COUNT(*) as transaction_count, SUM(amount) as total_amount')
            ->groupBy('method')
            ->orderByDesc('total_amount')
            ->get()
            ->map(function ($row) use ($methodLabels, $totalAmount) {
                $amount = (float) $row->total_amount;
                $percentage = $totalAmount > 0 ? ($amount / $totalAmount) * 100 : 0.0;

                return [
                    'method' => (int) $row->method,
                    'method_label' => $methodLabels[$row->method] ?? 'Unknown',
                    'transaction_count' => (int) $row->transaction_count,
                    'total_amount' => round($amount, 2),
                    'percentage' => round($percentage, 2),
                ];
            });

        return $this->successResponse([
            'range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'total_amount' => round((float) $totalAmount, 2),
            'dataset' => $dataset,
        ], 'Payment method distribution retrieved');
    }

    /**
     * @OA\Get(
     *     path="/api/reports/promotions",
     *     tags={"Reports"},
     *     summary="Promotion usage and totals",
     *     operationId="getPromotionsReport",
     *     description="Return total discount value applied and breakdown per promotion.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Promotion summary retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Promotion summary retrieved"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_discount", type="number", example=1200000),
     *                 @OA\Property(
     *                     property="dataset",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="promotion_id", type="string", example="PROMO001"),
     *                         @OA\Property(property="code", type="string", example="SALE10"),
     *                         @OA\Property(property="description", type="string", example="Giảm 10% cho hóa đơn trên 200k"),
     *                         @OA\Property(property="applied_count", type="integer", example=35),
     *                         @OA\Property(property="total_discount", type="number", example=350000),
     *                         @OA\Property(property="percentage", type="number", example=29.16)
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    #[Get('promotions', middleware : ['permission:statistics.view'])]
    public function promotions(RangeReportRequest $request): JsonResponse
    {
        [$start, $end] = $request->dateRange();

        $promotionQuery = InvoicePromotion::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_promotions.invoice_id')
            ->where('invoices.status', Invoice::STATUS_PAID)
            ->whereBetween(DB::raw('COALESCE(invoice_promotions.applied_at, invoice_promotions.created_at)'), [$start, $end]);

        $breakdownRows = (clone $promotionQuery)
            ->join('promotions', 'promotions.id', '=', 'invoice_promotions.promotion_id')
            ->select([
                'invoice_promotions.promotion_id',
                'promotions.code',
                'promotions.description',
            ])
            ->selectRaw('COUNT(*) as applied_count')
            ->selectRaw('SUM(invoices.total_amount * (COALESCE(invoice_promotions.discount_value, 0) / 100)) as total_discount_amount')
            ->groupBy('invoice_promotions.promotion_id', 'promotions.code', 'promotions.description')
            ->orderByDesc('total_discount_amount')
            ->get();

        $totalDiscount = (float) $breakdownRows->sum('total_discount_amount');

        $breakdown = $breakdownRows
            ->map(function ($row) use ($totalDiscount) {
                $amount = (float) $row->total_discount_amount;
                $percentage = $totalDiscount > 0 ? ($amount / $totalDiscount) * 100 : 0.0;

                return [
                    'promotion_id' => $row->promotion_id,
                    'code' => $row->code,
                    'description' => $row->description,
                    'applied_count' => (int) $row->applied_count,
                    'total_discount' => round($amount, 2),
                    'percentage' => round($percentage, 2),
                ];
            })
            ->values();

        return $this->successResponse([
            'range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'total_discount' => round($totalDiscount, 2),
            'dataset' => $breakdown,
        ], 'Promotion summary retrieved');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
     */
    private function aggregateRevenueByDay($query)
    {
        return $query
            ->selectRaw('DATE(paid_at) as period_date, SUM(amount) as total_amount, COUNT(*) as transaction_count')
            ->groupBy('period_date')
            ->orderBy('period_date')
            ->get()
            ->map(function ($row) {
                $start = Carbon::parse($row->period_date)->startOfDay();
                $end = $start->copy()->endOfDay();

                return [
                    'period_start' => $start->toDateTimeString(),
                    'period_end' => $end->toDateTimeString(),
                    'label' => $start->format('Y-m-d'),
                    'total_amount' => round((float) $row->total_amount, 2),
                    'transaction_count' => (int) $row->transaction_count,
                ];
            });
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
     */
    private function aggregateRevenueByWeek($query)
    {
        return $query
            ->selectRaw('YEAR(paid_at) as revenue_year, WEEK(paid_at, 1) as revenue_week, SUM(amount) as total_amount, COUNT(*) as transaction_count')
            ->groupBy('revenue_year', 'revenue_week')
            ->orderBy('revenue_year')
            ->orderBy('revenue_week')
            ->get()
            ->map(function ($row) {
                $start = Carbon::now()->setISODate($row->revenue_year, $row->revenue_week)->startOfWeek(Carbon::MONDAY);
                $end = $start->copy()->endOfWeek(Carbon::SUNDAY);

                return [
                    'period_start' => $start->toDateTimeString(),
                    'period_end' => $end->toDateTimeString(),
                    'label' => sprintf('%d-W%02d', $row->revenue_year, $row->revenue_week),
                    'total_amount' => round((float) $row->total_amount, 2),
                    'transaction_count' => (int) $row->transaction_count,
                ];
            });
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
     */
    private function aggregateRevenueByMonth($query)
    {
        return $query
            ->selectRaw('YEAR(paid_at) as revenue_year, MONTH(paid_at) as revenue_month, SUM(amount) as total_amount, COUNT(*) as transaction_count')
            ->groupBy('revenue_year', 'revenue_month')
            ->orderBy('revenue_year')
            ->orderBy('revenue_month')
            ->get()
            ->map(function ($row) {
                $start = Carbon::create($row->revenue_year, $row->revenue_month, 1)->startOfDay();
                $end = $start->copy()->endOfMonth();

                return [
                    'period_start' => $start->toDateTimeString(),
                    'period_end' => $end->toDateTimeString(),
                    'label' => $start->format('Y-m'),
                    'total_amount' => round((float) $row->total_amount, 2),
                    'transaction_count' => (int) $row->transaction_count,
                ];
            });
    }

    private function calculateTotalDiscount(Carbon $start, Carbon $end): float
    {
        $invoiceDiscount = Invoice::query()
            ->where('status', Invoice::STATUS_PAID)
            ->whereBetween('created_at', [$start, $end])
            ->sum('discount');

        $promotionDiscount = InvoicePromotion::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_promotions.invoice_id')
            ->where('invoices.status', Invoice::STATUS_PAID)
            ->whereBetween(DB::raw('COALESCE(invoice_promotions.applied_at, invoice_promotions.created_at)'), [$start, $end])
            ->sum('invoice_promotions.discount_value');

        return (float) $invoiceDiscount + (float) $promotionDiscount;
    }

    private function calculateGlobalDiscountAllocation(Carbon $start, Carbon $end, float $totalGross): float
    {
        if ($totalGross <= 0) {
            return 0.0;
        }

        return $this->calculateTotalDiscount($start, $end);
    }
}
