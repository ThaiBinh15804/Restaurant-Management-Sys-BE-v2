<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HealthController;

// Health check endpoint - for monitoring and load balancer health checks
Route::get('/health', [HealthController::class, 'check']);

// =============================================================================
// ROUTE ATTRIBUTES REGISTRATION
// =============================================================================
// All API routes are now automatically registered using Route Attributes.
// Routes are defined directly in the Controller classes using PHP attributes:
//
// - AuthController: /api/v1/auth/* routes (login, logout, me, refresh, sessions, revoke-token)
// - UserController: /api/v1/users/* routes (CRUD operations)
// - RoleController: /api/v1/roles/* routes (CRUD + permission management)
// - PermissionController: /api/v1/permissions/* routes (CRUD + role relationships)
//
// Route attributes are configured in config/route-attributes.php
// To see all registered routes, run: php artisan route:list
// =============================================================================

// Future Restaurant Management Modules (when implemented, add route attributes to controllers)
// - CategoryController: /api/v1/categories/*
// - ProductController: /api/v1/products/*  
// - OrderController: /api/v1/orders/*
// - TableController: /api/v1/tables/*
// - ReservationController: /api/v1/reservations/*
// - EmployeeController: /api/v1/employees/*
// - InventoryController: /api/v1/inventory/*
// - ReportController: /api/v1/reports/*