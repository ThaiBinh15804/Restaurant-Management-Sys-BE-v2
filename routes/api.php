<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HealthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check endpoint
Route::get('/health', [HealthController::class, 'check']);

// Version 1 API routes
Route::prefix('v1')->group(function () {
    
    // Public routes (no authentication required)
    Route::prefix('auth')->group(function () {
        // Authentication endpoints will be added here
        // Route::post('login', [AuthController::class, 'login']);
        // Route::post('register', [AuthController::class, 'register']);
        // Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    });
    
    // Protected routes (authentication required)
    Route::middleware('auth:sanctum')->group(function () {
        
        // User endpoints
        Route::get('/user', function (Request $request) {
            return response()->json([
                'status' => 'success',
                'data' => $request->user()
            ]);
        });
        
        // Restaurant Management endpoints will be added here
        // Route::apiResource('categories', CategoryController::class);
        // Route::apiResource('products', ProductController::class);
        // Route::apiResource('orders', OrderController::class);
        // Route::apiResource('tables', TableController::class);
        // Route::apiResource('reservations', ReservationController::class);
        // Route::apiResource('employees', EmployeeController::class);
        // Route::apiResource('inventory', InventoryController::class);
        
    });
    
});