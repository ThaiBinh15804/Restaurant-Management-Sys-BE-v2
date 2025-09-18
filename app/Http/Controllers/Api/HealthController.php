<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class HealthController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/health",
     *     summary="Check API health status",
     *     description="Returns the current status of the API",
     *     operationId="healthCheck",
     *     tags={"Health"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Restaurant Management System API is running"),
     *             @OA\Property(property="timestamp", type="string", format="date-time"),
     *             @OA\Property(property="version", type="string", example="1.0.0")
     *         )
     *     )
     * )
     */
    public function check()
    {
        return $this->successResponse([
            'timestamp' => now(),
            'version' => '1.0.0',
            'environment' => app()->environment(),
            'database' => $this->checkDatabase()
        ], 'Restaurant Management System API is running');
    }

    /**
     * Check database connection
     *
     * @return string
     */
    private function checkDatabase(): string
    {
        try {
            DB::connection()->getPdo();
            return 'connected';
        } catch (\Exception $e) {
            return 'disconnected';
        }
    }
}