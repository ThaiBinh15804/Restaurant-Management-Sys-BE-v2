<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HealthController;

Route::middleware(['web'])->group(function () {
    Route::get('/auth/google', [AuthController::class, 'googleRedirect']);
    Route::get('/auth/google/callback', [AuthController::class, 'googleCallback']);
});

// Health check endpoint - for monitoring and load balancer health checks
Route::get('/health', [HealthController::class, 'check']);

// =============================================================================
// ROUTE ATTRIBUTES REGISTRATION
// =============================================================================
// All API routes are now automatically registered using Route Attributes.
// Routes are defined directly in the Controller classes using PHP attributes:
//
// - AuthController: /auth/* routes (login, logout, me, refresh, sessions, revoke-token)
// - UserController: /users/* routes (CRUD operations)
// - RoleController: /roles/* routes (CRUD + permission management)
// - PermissionController: /permissions/* routes (CRUD + role relationships)
//
// Route attributes are configured in config/route-attributes.php
// To see all registered routes, run: php artisan route:list
// =============================================================================

// Future Restaurant Management Modules (when implemented, add route attributes to controllers)
// - CategoryController: /categories/*
// - ProductController: /products/*  
// - OrderController: /orders/*
// - TableController: /tables/*
// - ReservationController: /reservations/*
// - EmployeeController: /employees/*
// - InventoryController: /inventory/*
// - ReportController: /reports/*