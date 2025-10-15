<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StockExportStatusRequest;
use App\Http\Requests\Stock\StockExportStoreRequest;
use App\Http\Requests\Stock\StockExportUpdateRequest;
use App\Http\Requests\Stock\StockImportStoreRequest;
use App\Http\Requests\Stock\StockImportUpdateRequest;
use App\Http\Requests\Stock\StockLossStoreRequest;
use App\Http\Requests\Stock\StockLossUpdateRequest;
use App\Http\Requests\Stock\StockQueryRequest;
use App\Models\Ingredient;
use App\Models\StockExport;
use App\Models\StockExportDetail;
use App\Models\StockImport;
use App\Models\StockImportDetail;
use App\Models\StockLoss;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Patch;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Put;

/**
 * @OA\Tag(
 *     name="Stock Management",
 *     description="API Endpoints for Stock Import, Export, and Loss Management"
 * )
 */
#[Prefix('stocks')]
class StockController extends Controller
{
    // ==================== STOCK IMPORTS ====================

    /**
     * @OA\Get(
     *     path="/api/stocks/imports",
     *     tags={"Stock Management"},
     *     summary="List stock imports",
     *     description="Retrieve a paginated list of stock imports with optional filters",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="date_from", in="query", description="Filter from date", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", description="Filter to date", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="supplier_id", in="query", description="Filter by supplier", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Stock imports retrieved successfully")
     * )
     */
    #[Get('/imports', middleware: 'permission:stocks.view')]
    public function indexImports(StockQueryRequest $request): JsonResponse
    {
        $filters = $request->filters();

        $query = StockImport::with(['supplier', 'details.ingredient'])
            ->orderBy('import_date', 'desc')
            ->when(
                $filters['date_from'] ?? null,
                fn($q, $v) => $q->whereDate('import_date', '>=', $v)
            )
            ->when(
                $filters['date_to'] ?? null,
                fn($q, $v) => $q->whereDate('import_date', '<=', $v)
            )
            ->when(
                $filters['supplier_id'] ?? null,
                fn($q, $v) => $q->where('supplier_id', $v)
            );

        $perPage = $request->perPage();
        $paginator = $query->paginate($perPage);

        return $this->successResponse($paginator, 'Stock imports retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/stocks/imports",
     *     tags={"Stock Management"},
     *     summary="Create stock import",
     *     description="Create a new stock import with details",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"import_date","details"},
     *             @OA\Property(property="import_date", type="string", format="date", example="2025-10-14"),
     *             @OA\Property(property="supplier_id", type="string", example="SUP-000001"),
     *             @OA\Property(
     *                 property="details",
     *                 type="array",
     *                 @OA\Items(
     *                     required={"ingredient_id","ordered_quantity","received_quantity","unit_price"},
     *                     @OA\Property(property="ingredient_id", type="string", example="ING-000001"),
     *                     @OA\Property(property="ordered_quantity", type="number", format="float", example=100),
     *                     @OA\Property(property="received_quantity", type="number", format="float", example=100),
     *                     @OA\Property(property="unit_price", type="number", format="float", example=15000)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Stock import created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    #[Post('/imports', middleware: 'permission:stocks.create')]
    public function storeImport(StockImportStoreRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            $stockImport = DB::transaction(function () use ($data) {
                // Create stock import
                $import = StockImport::create([
                    'import_date' => $data['import_date'],
                    'supplier_id' => $data['supplier_id'] ?? null,
                    'total_amount' => 0,
                    'created_by' => auth('api')->id(),
                    'updated_by' => auth('api')->id(),
                ]);

                $totalAmount = 0;

                // Create import details
                foreach ($data['details'] as $detail) {
                    $totalPrice = $detail['received_quantity'] * $detail['unit_price'];
                    
                    StockImportDetail::create([
                        'stock_import_id' => $import->id,
                        'ingredient_id' => $detail['ingredient_id'],
                        'ordered_quantity' => $detail['ordered_quantity'],
                        'received_quantity' => $detail['received_quantity'],
                        'unit_price' => $detail['unit_price'],
                        'total_price' => $totalPrice,
                        'created_by' => auth('api')->id(),
                        'updated_by' => auth('api')->id(),
                    ]);

                    // Update ingredient stock
                    $ingredient = Ingredient::find($detail['ingredient_id']);
                    if ($ingredient) {
                        $ingredient->increment('current_stock', $detail['received_quantity']);
                    }

                    $totalAmount += $totalPrice;
                }

                // Update total amount
                $import->update(['total_amount' => $totalAmount]);

                return $import->load(['supplier', 'details.ingredient']);
            });

            Log::info('Stock import created successfully', [
                'import_id' => $stockImport->id,
                'created_by' => auth('api')->id(),
            ]);

            return $this->successResponse(
                $stockImport,
                'Stock import created successfully',
                201
            );
        } catch (\Exception $e) {
            Log::error('Failed to create stock import', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'Failed to create stock import: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/stocks/imports/{id}",
     *     tags={"Stock Management"},
     *     summary="Get stock import details",
     *     description="Retrieve detailed information about a specific stock import",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Stock import retrieved successfully"),
     *     @OA\Response(response=404, description="Stock import not found")
     * )
     */
    #[Get('/imports/{id}', middleware: 'permission:stocks.view')]
    public function showImport(string $id): JsonResponse
    {
        $stockImport = StockImport::with(['supplier', 'details.ingredient'])->find($id);

        if (!$stockImport) {
            return $this->errorResponse('Stock import not found', [], 404);
        }

        return $this->successResponse($stockImport, 'Stock import retrieved successfully');
    }

    /**
     * @OA\Put(
     *     path="/api/stocks/imports/{id}",
     *     tags={"Stock Management"},
     *     summary="Update stock import",
     *     description="Update stock import information",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="import_date", type="string", format="date"),
     *             @OA\Property(property="supplier_id", type="string"),
     *             @OA\Property(
     *                 property="details",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="string", description="Detail ID (for update)"),
     *                     @OA\Property(property="ingredient_id", type="string"),
     *                     @OA\Property(property="ordered_quantity", type="number", format="float"),
     *                     @OA\Property(property="received_quantity", type="number", format="float"),
     *                     @OA\Property(property="unit_price", type="number", format="float"),
     *                     @OA\Property(property="delete", type="boolean", description="Mark for deletion")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Stock import updated successfully"),
     *     @OA\Response(response=404, description="Stock import not found")
     * )
     */
    #[Put('/imports/{id}', middleware: 'permission:stocks.edit')]
    public function updateImport(StockImportUpdateRequest $request, string $id): JsonResponse
    {
        try {
            $stockImport = StockImport::with('details')->find($id);

            if (!$stockImport) {
                return $this->errorResponse('Stock import not found', [], 404);
            }

            $data = $request->validated();

            DB::transaction(function () use ($stockImport, $data) {
                // Update import header
                if (isset($data['import_date']) || isset($data['supplier_id'])) {
                    $stockImport->update([
                        'import_date' => $data['import_date'] ?? $stockImport->import_date,
                        'supplier_id' => $data['supplier_id'] ?? $stockImport->supplier_id,
                        'updated_by' => auth('api')->id(),
                    ]);
                }

                // Update details if provided
                if (isset($data['details'])) {
                    $totalAmount = 0;

                    foreach ($data['details'] as $detail) {
                        // Delete detail if marked
                        if (isset($detail['delete']) && $detail['delete']) {
                            if (isset($detail['id'])) {
                                $existingDetail = StockImportDetail::find($detail['id']);
                                if ($existingDetail) {
                                    // Rollback stock
                                    $ingredient = Ingredient::find($existingDetail->ingredient_id);
                                    if ($ingredient) {
                                        $ingredient->decrement('current_stock', (float) $existingDetail->received_quantity);
                                    }
                                    $existingDetail->delete();
                                }
                            }
                            continue;
                        }

                        $totalPrice = $detail['received_quantity'] * $detail['unit_price'];

                        // Update existing detail
                        if (isset($detail['id'])) {
                            $existingDetail = StockImportDetail::find($detail['id']);
                            if ($existingDetail) {
                                // Rollback old stock
                                $ingredient = Ingredient::find($existingDetail->ingredient_id);
                                if ($ingredient) {
                                    $ingredient->decrement('current_stock', (float) $existingDetail->received_quantity);
                                }

                                // Update detail
                                $existingDetail->update([
                                    'ingredient_id' => $detail['ingredient_id'],
                                    'ordered_quantity' => $detail['ordered_quantity'],
                                    'received_quantity' => $detail['received_quantity'],
                                    'unit_price' => $detail['unit_price'],
                                    'total_price' => $totalPrice,
                                    'updated_by' => auth('api')->id(),
                                ]);

                                // Add new stock
                                $ingredient = Ingredient::find($detail['ingredient_id']);
                                if ($ingredient) {
                                    $ingredient->increment('current_stock', $detail['received_quantity']);
                                }

                                $totalAmount += $totalPrice;
                            }
                        } else {
                            // Create new detail
                            StockImportDetail::create([
                                'stock_import_id' => $stockImport->id,
                                'ingredient_id' => $detail['ingredient_id'],
                                'ordered_quantity' => $detail['ordered_quantity'],
                                'received_quantity' => $detail['received_quantity'],
                                'unit_price' => $detail['unit_price'],
                                'total_price' => $totalPrice,
                                'created_by' => auth('api')->id(),
                                'updated_by' => auth('api')->id(),
                            ]);

                            // Update ingredient stock
                            $ingredient = Ingredient::find($detail['ingredient_id']);
                            if ($ingredient) {
                                $ingredient->increment('current_stock', $detail['received_quantity']);
                            }

                            $totalAmount += $totalPrice;
                        }
                    }

                    // Recalculate total amount
                    $stockImport->update(['total_amount' => $totalAmount]);
                }
            });

            Log::info('Stock import updated successfully', [
                'import_id' => $id,
                'updated_by' => auth('api')->id(),
            ]);

            return $this->successResponse(
                $stockImport->fresh(['supplier', 'details.ingredient']),
                'Stock import updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to update stock import', [
                'import_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to update stock import: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/stocks/imports/{id}",
     *     tags={"Stock Management"},
     *     summary="Delete stock import",
     *     description="Delete a stock import and rollback stock changes",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Stock import deleted successfully"),
     *     @OA\Response(response=404, description="Stock import not found")
     * )
     */
    #[Delete('/imports/{id}', middleware: 'permission:stocks.delete')]
    public function destroyImport(string $id): JsonResponse
    {
        try {
            $stockImport = StockImport::with('details')->find($id);

            if (!$stockImport) {
                return $this->errorResponse('Stock import not found', [], 404);
            }

            DB::transaction(function () use ($stockImport) {
                // Rollback stock for all details
                foreach ($stockImport->details as $detail) {
                    $ingredient = Ingredient::find($detail->ingredient_id);
                    if ($ingredient) {
                        $ingredient->decrement('current_stock', $detail->received_quantity);
                    }
                }

                $stockImport->delete();
            });

            Log::info('Stock import deleted', ['import_id' => $id]);

            return $this->successResponse([], 'Stock import deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete stock import', [
                'import_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to delete stock import: ' . $e->getMessage(),
                500
            );
        }
    }

    // ==================== STOCK EXPORTS ====================

    /**
     * @OA\Get(
     *     path="/api/stocks/exports",
     *     tags={"Stock Management"},
     *     summary="List stock exports",
     *     description="Retrieve a paginated list of stock exports with optional filters",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="status", in="query", description="0=Draft, 1=Approved, 2=Completed", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Stock exports retrieved successfully")
     * )
     */
    #[Get('/exports', middleware: 'permission:stocks.view')]
    public function indexExports(StockQueryRequest $request): JsonResponse
    {
        $filters = $request->filters();

        $query = StockExport::with(['details.ingredient'])
            ->orderBy('export_date', 'desc')
            ->when(
                $filters['date_from'] ?? null,
                fn($q, $v) => $q->whereDate('export_date', '>=', $v)
            )
            ->when(
                $filters['date_to'] ?? null,
                fn($q, $v) => $q->whereDate('export_date', '<=', $v)
            )
            ->when(
                isset($filters['status']) ? $filters['status'] !== null : false,
                fn($q) => $q->where('status', $filters['status'])
            );

        $perPage = $request->perPage();
        $paginator = $query->paginate($perPage);

        return $this->successResponse($paginator, 'Stock exports retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/stocks/exports",
     *     tags={"Stock Management"},
     *     summary="Create stock export",
     *     description="Create a new stock export with details",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"export_date","details"},
     *             @OA\Property(property="export_date", type="string", format="date", example="2025-10-14"),
     *             @OA\Property(property="purpose", type="string", example="Kitchen operations"),
     *             @OA\Property(property="status", type="integer", enum={0, 1, 2}, example=0, description="0=Draft, 1=Approved, 2=Completed"),
     *             @OA\Property(
     *                 property="details",
     *                 type="array",
     *                 @OA\Items(
     *                     required={"ingredient_id","quantity"},
     *                     @OA\Property(property="ingredient_id", type="string", example="ING-000001"),
     *                     @OA\Property(property="quantity", type="number", format="float", example=10)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Stock export created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    #[Post('/exports', middleware: 'permission:stocks.create')]
    public function storeExport(StockExportStoreRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            $stockExport = DB::transaction(function () use ($data) {
                // Create stock export
                $export = StockExport::create([
                    'export_date' => $data['export_date'],
                    'purpose' => $data['purpose'] ?? null,
                    'status' => $data['status'] ?? StockExport::STATUS_DRAFT,
                    'created_by' => auth('api')->id(),
                    'updated_by' => auth('api')->id(),
                ]);

                // Create export details
                foreach ($data['details'] as $detail) {
                    StockExportDetail::create([
                        'stock_export_id' => $export->id,
                        'ingredient_id' => $detail['ingredient_id'],
                        'quantity' => $detail['quantity'],
                        'created_by' => auth('api')->id(),
                        'updated_by' => auth('api')->id(),
                    ]);

                    // Update ingredient stock only if status is COMPLETED
                    if ($export->status == StockExport::STATUS_COMPLETED) {
                        $ingredient = Ingredient::find($detail['ingredient_id']);
                        if ($ingredient) {
                            $ingredient->decrement('current_stock', $detail['quantity']);
                        }
                    }
                }

                return $export->load(['details.ingredient']);
            });

            Log::info('Stock export created successfully', [
                'export_id' => $stockExport->id,
                'created_by' => auth('api')->id(),
            ]);

            return $this->successResponse(
                $stockExport,
                'Stock export created successfully',
                201
            );
        } catch (\Exception $e) {
            Log::error('Failed to create stock export', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'Failed to create stock export: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/stocks/exports/{id}",
     *     tags={"Stock Management"},
     *     summary="Get stock export details",
     *     description="Retrieve detailed information about a specific stock export",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Stock export retrieved successfully"),
     *     @OA\Response(response=404, description="Stock export not found")
     * )
     */
    #[Get('/exports/{id}', middleware: 'permission:stocks.view')]
    public function showExport(string $id): JsonResponse
    {
        $stockExport = StockExport::with(['details.ingredient'])->find($id);

        if (!$stockExport) {
            return $this->errorResponse('Stock export not found', [], 404);
        }

        return $this->successResponse($stockExport, 'Stock export retrieved successfully');
    }

    /**
     * @OA\Put(
     *     path="/api/stocks/exports/{id}",
     *     tags={"Stock Management"},
     *     summary="Update stock export",
     *     description="Update stock export information",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="export_date", type="string", format="date"),
     *             @OA\Property(property="purpose", type="string"),
     *             @OA\Property(property="status", type="integer", enum={0, 1, 2}),
     *             @OA\Property(
     *                 property="details",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="string"),
     *                     @OA\Property(property="ingredient_id", type="string"),
     *                     @OA\Property(property="quantity", type="number", format="float"),
     *                     @OA\Property(property="delete", type="boolean")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Stock export updated successfully"),
     *     @OA\Response(response=404, description="Stock export not found")
     * )
     */
    #[Put('/exports/{id}', middleware: 'permission:stocks.edit')]
    public function updateExport(StockExportUpdateRequest $request, string $id): JsonResponse
    {
        try {
            $stockExport = StockExport::with('details')->find($id);

            if (!$stockExport) {
                return $this->errorResponse('Stock export not found', [], 404);
            }

            $data = $request->validated();
            $oldStatus = $stockExport->status;

            DB::transaction(function () use ($stockExport, $data, $oldStatus) {
                // Update export header
                $updateData = [];
                if (isset($data['export_date'])) {
                    $updateData['export_date'] = $data['export_date'];
                }
                if (isset($data['purpose'])) {
                    $updateData['purpose'] = $data['purpose'];
                }
                if (isset($data['status'])) {
                    $updateData['status'] = $data['status'];
                }
                
                if (!empty($updateData)) {
                    $updateData['updated_by'] = auth('api')->id();
                    $stockExport->update($updateData);
                }

                // Handle status change impact on stock
                $newStatus = $stockExport->status;
                
                // If changing from non-COMPLETED to COMPLETED
                if ($oldStatus != StockExport::STATUS_COMPLETED && $newStatus == StockExport::STATUS_COMPLETED) {
                    foreach ($stockExport->details as $detail) {
                        $ingredient = Ingredient::find($detail->ingredient_id);
                        if ($ingredient) {
                            $ingredient->decrement('current_stock', $detail->quantity);
                        }
                    }
                }
                
                // If changing from COMPLETED to non-COMPLETED (rollback)
                if ($oldStatus == StockExport::STATUS_COMPLETED && $newStatus != StockExport::STATUS_COMPLETED) {
                    foreach ($stockExport->details as $detail) {
                        $ingredient = Ingredient::find($detail->ingredient_id);
                        if ($ingredient) {
                            $ingredient->increment('current_stock', $detail->quantity);
                        }
                    }
                }

                // Update details if provided
                if (isset($data['details'])) {
                    foreach ($data['details'] as $detail) {
                        // Delete detail if marked
                        if (isset($detail['delete']) && $detail['delete']) {
                            if (isset($detail['id'])) {
                                $existingDetail = StockExportDetail::find($detail['id']);
                                if ($existingDetail) {
                                    // Rollback stock if export was completed
                                    if ($stockExport->status == StockExport::STATUS_COMPLETED) {
                                        $ingredient = Ingredient::find($existingDetail->ingredient_id);
                                        if ($ingredient) {
                                            $ingredient->increment('current_stock', (float) $existingDetail->quantity);
                                        }
                                    }
                                    $existingDetail->delete();
                                }
                            }
                            continue;
                        }

                        // Update existing detail
                        if (isset($detail['id'])) {
                            $existingDetail = StockExportDetail::find($detail['id']);
                            if ($existingDetail) {
                                // Rollback old stock if completed
                                if ($stockExport->status == StockExport::STATUS_COMPLETED) {
                                    $ingredient = Ingredient::find($existingDetail->ingredient_id);
                                    if ($ingredient) {
                                        $ingredient->increment('current_stock', (float) $existingDetail->quantity);
                                    }
                                }

                                // Update detail
                                $existingDetail->update([
                                    'ingredient_id' => $detail['ingredient_id'],
                                    'quantity' => $detail['quantity'],
                                    'updated_by' => auth('api')->id(),
                                ]);

                                // Apply new stock if completed
                                if ($stockExport->status == StockExport::STATUS_COMPLETED) {
                                    $ingredient = Ingredient::find($detail['ingredient_id']);
                                    if ($ingredient) {
                                        $ingredient->decrement('current_stock', $detail['quantity']);
                                    }
                                }
                            }
                        } else {
                            // Create new detail
                            StockExportDetail::create([
                                'stock_export_id' => $stockExport->id,
                                'ingredient_id' => $detail['ingredient_id'],
                                'quantity' => $detail['quantity'],
                                'created_by' => auth('api')->id(),
                                'updated_by' => auth('api')->id(),
                            ]);

                            // Update stock if completed
                            if ($stockExport->status == StockExport::STATUS_COMPLETED) {
                                $ingredient = Ingredient::find($detail['ingredient_id']);
                                if ($ingredient) {
                                    $ingredient->decrement('current_stock', $detail['quantity']);
                                }
                            }
                        }
                    }
                }
            });

            Log::info('Stock export updated successfully', [
                'export_id' => $id,
                'updated_by' => auth('api')->id(),
            ]);

            return $this->successResponse(
                $stockExport->fresh(['details.ingredient']),
                'Stock export updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to update stock export', [
                'export_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to update stock export: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/stocks/exports/{id}/status",
     *     tags={"Stock Management"},
     *     summary="Change stock export status",
     *     description="Change the status of a stock export (Draft/Approved/Completed)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="integer", enum={0, 1, 2}, description="0=Draft, 1=Approved, 2=Completed")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Status updated successfully"),
     *     @OA\Response(response=404, description="Stock export not found")
     * )
     */
    #[Patch('/exports/{id}/status', middleware: 'permission:stocks.edit')]
    public function updateExportStatus(StockExportStatusRequest $request, string $id): JsonResponse
    {
        try {
            $stockExport = StockExport::with('details')->find($id);

            if (!$stockExport) {
                return $this->errorResponse('Stock export not found', [], 404);
            }

            $newStatus = $request->input('status');
            $oldStatus = $stockExport->status;

            DB::transaction(function () use ($stockExport, $newStatus, $oldStatus) {
                // If changing from non-COMPLETED to COMPLETED
                if ($oldStatus != StockExport::STATUS_COMPLETED && $newStatus == StockExport::STATUS_COMPLETED) {
                    // Validate stock availability
                    foreach ($stockExport->details as $detail) {
                        $ingredient = Ingredient::find($detail->ingredient_id);
                        if (!$ingredient || $ingredient->current_stock < $detail->quantity) {
                            throw new \Exception(
                                "Insufficient stock for {$ingredient->name}. Available: {$ingredient->current_stock} {$ingredient->unit}"
                            );
                        }
                    }

                    // Deduct stock
                    foreach ($stockExport->details as $detail) {
                        $ingredient = Ingredient::find($detail->ingredient_id);
                        if ($ingredient) {
                            $ingredient->decrement('current_stock', $detail->quantity);
                        }
                    }
                }

                // If changing from COMPLETED to non-COMPLETED (rollback)
                if ($oldStatus == StockExport::STATUS_COMPLETED && $newStatus != StockExport::STATUS_COMPLETED) {
                    foreach ($stockExport->details as $detail) {
                        $ingredient = Ingredient::find($detail->ingredient_id);
                        if ($ingredient) {
                            $ingredient->increment('current_stock', $detail->quantity);
                        }
                    }
                }

                $stockExport->update([
                    'status' => $newStatus,
                    'updated_by' => auth('api')->id(),
                ]);
            });

            Log::info('Stock export status updated', [
                'export_id' => $id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);

            return $this->successResponse(
                $stockExport->fresh(['details.ingredient']),
                'Stock export status updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to update stock export status', [
                'export_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to update status: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/stocks/exports/{id}",
     *     tags={"Stock Management"},
     *     summary="Delete stock export",
     *     description="Delete a stock export and rollback stock if completed",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Stock export deleted successfully"),
     *     @OA\Response(response=404, description="Stock export not found")
     * )
     */
    #[Delete('/exports/{id}', middleware: 'permission:stocks.delete')]
    public function destroyExport(string $id): JsonResponse
    {
        try {
            $stockExport = StockExport::with('details')->find($id);

            if (!$stockExport) {
                return $this->errorResponse('Stock export not found', [], 404);
            }

            DB::transaction(function () use ($stockExport) {
                // Rollback stock if export was completed
                if ($stockExport->status == StockExport::STATUS_COMPLETED) {
                    foreach ($stockExport->details as $detail) {
                        $ingredient = Ingredient::find($detail->ingredient_id);
                        if ($ingredient) {
                            $ingredient->increment('current_stock', $detail->quantity);
                        }
                    }
                }

                $stockExport->delete();
            });

            Log::info('Stock export deleted', ['export_id' => $id]);

            return $this->successResponse([], 'Stock export deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete stock export', [
                'export_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to delete stock export: ' . $e->getMessage(),
                500
            );
        }
    }

    // ==================== STOCK LOSSES ====================

    /**
     * @OA\Get(
     *     path="/api/stocks/losses",
     *     tags={"Stock Management"},
     *     summary="List stock losses",
     *     description="Retrieve a paginated list of stock losses",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="ingredient_id", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Stock losses retrieved successfully")
     * )
     */
    #[Get('/losses', middleware: 'permission:stocks.view')]
    public function indexLosses(StockQueryRequest $request): JsonResponse
    {
        $filters = $request->filters();

        $query = StockLoss::with(['ingredient', 'employee.user'])
            ->orderBy('loss_date', 'desc')
            ->when(
                $filters['date_from'] ?? null,
                fn($q, $v) => $q->whereDate('loss_date', '>=', $v)
            )
            ->when(
                $filters['date_to'] ?? null,
                fn($q, $v) => $q->whereDate('loss_date', '<=', $v)
            )
            ->when(
                $filters['ingredient_id'] ?? null,
                fn($q, $v) => $q->where('ingredient_id', $v)
            );

        $perPage = $request->perPage();
        $paginator = $query->paginate($perPage);

        return $this->successResponse($paginator, 'Stock losses retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/stocks/losses",
     *     tags={"Stock Management"},
     *     summary="Record stock loss",
     *     description="Record a new stock loss",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ingredient_id","quantity","loss_date"},
     *             @OA\Property(property="ingredient_id", type="string", example="ING-000001"),
     *             @OA\Property(property="quantity", type="number", format="float", example=5),
     *             @OA\Property(property="reason", type="string", example="Expired"),
     *             @OA\Property(property="loss_date", type="string", format="date", example="2025-10-14"),
     *             @OA\Property(property="employee_id", type="string", example="EMP-000001")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Stock loss recorded successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    #[Post('/losses', middleware: 'permission:stocks.create')]
    public function storeLoss(StockLossStoreRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            $stockLoss = DB::transaction(function () use ($data) {
                // Create stock loss
                $loss = StockLoss::create([
                    'ingredient_id' => $data['ingredient_id'],
                    'quantity' => $data['quantity'],
                    'reason' => $data['reason'] ?? null,
                    'loss_date' => $data['loss_date'],
                    'employee_id' => $data['employee_id'] ?? auth('api')->user()->employeeProfile?->id,
                    'created_by' => auth('api')->id(),
                    'updated_by' => auth('api')->id(),
                ]);

                // Update ingredient stock
                $ingredient = Ingredient::find($data['ingredient_id']);
                if ($ingredient) {
                    $ingredient->decrement('current_stock', $data['quantity']);
                }

                return $loss->load(['ingredient', 'employee.user']);
            });

            Log::info('Stock loss recorded successfully', [
                'loss_id' => $stockLoss->id,
                'created_by' => auth('api')->id(),
            ]);

            return $this->successResponse(
                $stockLoss,
                'Stock loss recorded successfully',
                201
            );
        } catch (\Exception $e) {
            Log::error('Failed to record stock loss', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'Failed to record stock loss: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/stocks/losses/{id}",
     *     tags={"Stock Management"},
     *     summary="Get stock loss details",
     *     description="Retrieve detailed information about a specific stock loss",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Stock loss retrieved successfully"),
     *     @OA\Response(response=404, description="Stock loss not found")
     * )
     */
    #[Get('/losses/{id}', middleware: 'permission:stocks.view')]
    public function showLoss(string $id): JsonResponse
    {
        $stockLoss = StockLoss::with(['ingredient', 'employee.user'])->find($id);

        if (!$stockLoss) {
            return $this->errorResponse('Stock loss not found', [], 404);
        }

        return $this->successResponse($stockLoss, 'Stock loss retrieved successfully');
    }

    /**
     * @OA\Put(
     *     path="/api/stocks/losses/{id}",
     *     tags={"Stock Management"},
     *     summary="Update stock loss",
     *     description="Update stock loss information",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="ingredient_id", type="string"),
     *             @OA\Property(property="quantity", type="number", format="float"),
     *             @OA\Property(property="reason", type="string"),
     *             @OA\Property(property="loss_date", type="string", format="date"),
     *             @OA\Property(property="employee_id", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Stock loss updated successfully"),
     *     @OA\Response(response=404, description="Stock loss not found")
     * )
     */
    #[Put('/losses/{id}', middleware: 'permission:stocks.edit')]
    public function updateLoss(StockLossUpdateRequest $request, string $id): JsonResponse
    {
        try {
            $stockLoss = StockLoss::find($id);

            if (!$stockLoss) {
                return $this->errorResponse('Stock loss not found', [], 404);
            }

            $data = $request->validated();

            DB::transaction(function () use ($stockLoss, $data) {
                $oldQuantity = $stockLoss->quantity;
                $oldIngredientId = $stockLoss->ingredient_id;

                // Rollback old stock
                $oldIngredient = Ingredient::find($oldIngredientId);
                if ($oldIngredient) {
                    $oldIngredient->increment('current_stock', (float) $oldQuantity);
                }

                // Update loss record
                $stockLoss->update([
                    'ingredient_id' => $data['ingredient_id'] ?? $stockLoss->ingredient_id,
                    'quantity' => $data['quantity'] ?? $stockLoss->quantity,
                    'reason' => $data['reason'] ?? $stockLoss->reason,
                    'loss_date' => $data['loss_date'] ?? $stockLoss->loss_date,
                    'employee_id' => $data['employee_id'] ?? $stockLoss->employee_id,
                    'updated_by' => auth('api')->id(),
                ]);

                // Apply new stock
                $newIngredient = Ingredient::find($stockLoss->ingredient_id);
                if ($newIngredient) {
                    $newIngredient->decrement('current_stock', (float) $stockLoss->quantity);
                }
            });

            Log::info('Stock loss updated successfully', [
                'loss_id' => $id,
                'updated_by' => auth('api')->id(),
            ]);

            return $this->successResponse(
                $stockLoss->fresh(['ingredient', 'employee.user']),
                'Stock loss updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to update stock loss', [
                'loss_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to update stock loss: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/stocks/losses/{id}",
     *     tags={"Stock Management"},
     *     summary="Delete stock loss",
     *     description="Delete a stock loss record and restore stock",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Stock loss deleted successfully"),
     *     @OA\Response(response=404, description="Stock loss not found")
     * )
     */
    #[Delete('/losses/{id}', middleware: 'permission:stocks.delete')]
    public function destroyLoss(string $id): JsonResponse
    {
        try {
            $stockLoss = StockLoss::find($id);

            if (!$stockLoss) {
                return $this->errorResponse('Stock loss not found', [], 404);
            }

            DB::transaction(function () use ($stockLoss) {
                // Restore stock
                $ingredient = Ingredient::find($stockLoss->ingredient_id);
                if ($ingredient) {
                    $ingredient->increment('current_stock', (float) $stockLoss->quantity);
                }

                $stockLoss->delete();
            });

            Log::info('Stock loss deleted', ['loss_id' => $id]);

            return $this->successResponse([], 'Stock loss deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete stock loss', [
                'loss_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to delete stock loss: ' . $e->getMessage(),
                500
            );
        }
    }
}
