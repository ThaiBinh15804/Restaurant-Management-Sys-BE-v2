<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Api\Traits\ApiResponseTrait;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Restaurant Management System API",
 *     description="API documentation for Restaurant Management System Backend",
 *     @OA\Contact(
 *         email="admin@restaurant.com"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 * 
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="Restaurant Management API Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter JWT token in format: Bearer {token}"
 * )
 */
abstract class Controller
{
    use ApiResponseTrait;
}
