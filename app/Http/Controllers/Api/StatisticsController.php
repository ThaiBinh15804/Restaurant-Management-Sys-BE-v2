<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\TableSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;

/**
 * @OA\Tag(
 *     name="Statistics",
 *     description="API Endpoints for General Statistics"
 * )
 */
#[Prefix('home')]
class StatisticsController extends Controller
{
    use \App\Http\Controllers\Api\Traits\ApiResponseTrait;

    /**
     * @OA\Get(
     *     path="/api/home/statistics",
     *     tags={"Statistics"},
     *     summary="Get general statistics",
     *     description="Retrieve general statistics for the restaurant system including counts of employees, customers, orders, reservations, and active table sessions",
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Statistics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_employees", type="integer", example=50),
     *                 @OA\Property(property="total_customers", type="integer", example=200),
     *                 @OA\Property(property="total_orders", type="integer", example=1000),
     *                 @OA\Property(property="total_reservations", type="integer", example=150),
     *                 @OA\Property(property="active_table_sessions", type="integer", example=10),
     *                 @OA\Property(property="total_revenue", type="number", format="float", example=50000.00)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to retrieve statistics",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve statistics"),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"), example=[])
     *         )
     *     )
     * )
     */
    #[Get('statistics')]
    public function getStatistics(): JsonResponse
    {
        try {
            $statistics = [
                'total_customers' => Customer::count(),
                'total_orders' => Order::count(),
                'total_reservations' => Reservation::count(),
                'active_table_sessions' => TableSession::where('status', 1)->count(), 
            ];

            return $this->successResponse($statistics, 'Statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve statistics: ' . $e->getMessage(),
                [],
                500
            );
        }
    }
}