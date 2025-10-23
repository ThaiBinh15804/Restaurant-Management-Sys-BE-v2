<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePromotion;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\TableSession;
use App\Models\TableSessionDiningTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class TableSessionService
{
    /**
     * Gộp nhiều bàn vào một bàn chính
     *
     * @param array $sourceTableSessionIds Danh sách ID các session cần gộp
     * @param string $targetTableSessionId ID session đích (bàn chính)
     * @param string $employeeId ID nhân viên thực hiện
     * @return array
     */
    public function mergeTables(array $sourceTableSessionIds, string $targetTableSessionId, string $employeeId): array
    {
        DB::beginTransaction();

        try {
            // 1. Validate các session
            $validation = $this->validateMerge($sourceTableSessionIds, $targetTableSessionId);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message'],
                    'errors' => $validation['errors'] ?? []
                ];
            }

            $sourceSessions = $validation['source_sessions'];
            $targetSession = $validation['target_session'];

            // 2. Kiểm tra invoice của target session và source sessions
            $targetInvoice = Invoice::where('table_session_id', $targetTableSessionId)
                ->whereNull('merged_invoice_id')
                ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID])
                ->first();

            $sourceInvoices = Invoice::whereIn('table_session_id', $sourceTableSessionIds)
                ->whereNull('merged_invoice_id')
                ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID])
                ->get();

            $hasSourceInvoices = $sourceInvoices->isNotEmpty();
            $hasTargetInvoice = !is_null($targetInvoice);

            // 3. Validate logic gộp invoice
            if (!$hasTargetInvoice && $hasSourceInvoices) {
                // Bàn đích không có invoice nhưng bàn nguồn có invoice
                return [
                    'success' => false,
                    'message' => 'Target table does not have an invoice. Please create a draft invoice for the target table before merging.',
                    'errors' => [
                        'target_invoice' => [
                            'Target session must have an invoice (draft or active) when source sessions have invoices. Create a draft invoice first.'
                        ]
                    ]
                ];
            }

            // 4. Chuyển tất cả orders sang target session (luôn thực hiện)
            Order::whereIn('table_session_id', $sourceTableSessionIds)
                ->update([
                    'table_session_id' => $targetTableSessionId,
                    'updated_by' => $employeeId,
                    'updated_at' => now()
                ]);

            $mergedInvoice = null;

            // 5. Xử lý invoice dựa trên trường hợp
            if ($hasTargetInvoice) {
                // Trường hợp: Bàn đích có invoice (bàn nguồn có thể có hoặc không)
                $mergedInvoice = $targetInvoice;

                if ($hasSourceInvoices) {
                    // Gộp invoice nguồn vào invoice đích

                    // 5.1. Tính tổng total_amount từ các invoice nguồn
                    $sourceTotal = $sourceInvoices->sum('total_amount');
                    $newTotalAmount = $targetInvoice->total_amount + $sourceTotal;

                    // 5.2. Giảm giá và thuế áp dụng theo invoice bàn đích (giữ nguyên)
                    $discount = $targetInvoice->discount;
                    $tax = $targetInvoice->tax;

                    // 5.3. Tính final_amount với discount và tax của bàn đích
                    $finalAmount = round($newTotalAmount * (1 - $discount / 100) * (1 + $tax / 100), 2);

                    // 5.4. Tính tổng payment đã hoàn thành từ tất cả invoice (target + sources)
                    $targetPaid = Payment::where('invoice_id', $targetInvoice->id)
                        ->where('status', Payment::STATUS_COMPLETED)
                        ->sum('amount');

                    $sourcePaid = Payment::whereIn('invoice_id', $sourceInvoices->pluck('id'))
                        ->where('status', Payment::STATUS_COMPLETED)
                        ->sum('amount');

                    $totalPaid = round($targetPaid + $sourcePaid, 2);

                    // 5.5. Xác định status mới
                    $newStatus = Invoice::STATUS_UNPAID;
                    if ($totalPaid >= $finalAmount) {
                        $newStatus = Invoice::STATUS_PAID;
                    } elseif ($totalPaid > 0) {
                        $newStatus = Invoice::STATUS_PARTIALLY_PAID;
                    }

                    // 5.6. Cập nhật invoice đích
                    $mergedInvoice->update([
                        'total_amount' => $newTotalAmount,
                        'discount' => $discount,
                        'tax' => $tax,
                        'final_amount' => $finalAmount,
                        'status' => $newStatus,
                        'operation_type' => Invoice::OPERATION_MERGE,
                        'source_invoice_ids' => $sourceInvoices->pluck('id')->toArray(),
                        'operation_notes' => "Merged from " . count($sourceInvoices) . " source invoices. Total paid: {$totalPaid}",
                        'operation_at' => now(),
                        'operation_by' => $employeeId,
                        'updated_by' => $employeeId
                    ]);

                    // 5.7. Chuyển payment từ invoice nguồn sang invoice đích
                    Payment::whereIn('invoice_id', $sourceInvoices->pluck('id'))
                        ->where('status', Payment::STATUS_COMPLETED)
                        ->update([
                            'invoice_id' => $mergedInvoice->id,
                            'updated_by' => $employeeId,
                            'updated_at' => now()
                        ]);

                    // 5.8. Sao chép promotions từ invoice nguồn (nếu có)
                    $this->copyPromotionsToMergedInvoice($sourceInvoices, $mergedInvoice, $employeeId);

                    // 5.9. Đánh dấu invoice nguồn là đã merged
                    foreach ($sourceInvoices as $sourceInvoice) {
                        $sourceInvoice->update([
                            'status' => Invoice::STATUS_MERGED,
                            'merged_invoice_id' => $mergedInvoice->id,
                            'updated_by' => $employeeId
                        ]);
                    }

                    Log::info('Merged invoices into target invoice', [
                        'target_invoice_id' => $mergedInvoice->id,
                        'source_invoice_ids' => $sourceInvoices->pluck('id')->toArray(),
                        'new_total' => $newTotalAmount,
                        'new_final' => $finalAmount,
                        'total_paid' => $totalPaid,
                        'new_status' => $newStatus
                    ]);
                } else {
                    // Bàn đích có invoice, bàn nguồn không có invoice nhưng có order
                    $mergedInvoice = $targetInvoice;

                    // Lấy tất cả order hiện tại của target session (bao gồm order từ bàn nguồn vừa merge)
                    $orders = Order::where('table_session_id', $targetTableSessionId)->get();
                    $newTotalAmount = $orders->sum('total_amount');

                    // Giữ nguyên discount & tax của bàn đích
                    $discount = $targetInvoice->discount;
                    $tax = $targetInvoice->tax;

                    // Tính final_amount mới
                    $finalAmount = round($newTotalAmount * (1 - $discount / 100) * (1 + $tax / 100), 2);

                    // Lấy tổng payment đã thanh toán trước đó
                    $totalPaid = Payment::where('invoice_id', $targetInvoice->id)
                        ->where('status', Payment::STATUS_COMPLETED)
                        ->sum('amount');

                    $newStatus = Invoice::STATUS_UNPAID;
                    if ($totalPaid >= $finalAmount) {
                        $newStatus = Invoice::STATUS_PAID;
                    } elseif ($totalPaid > 0) {
                        $newStatus = Invoice::STATUS_PARTIALLY_PAID;
                    }

                    // Cập nhật invoice đích
                    $mergedInvoice->update([
                        'total_amount' => $newTotalAmount,
                        'discount' => $discount,
                        'tax' => $tax,
                        'final_amount' => $finalAmount,
                        'status' => $newStatus,
                        'operation_type' => Invoice::OPERATION_MERGE,
                        'operation_notes' => "Merged orders from sessions without invoices. Total paid: {$totalPaid}",
                        'operation_at' => now(),
                        'operation_by' => $employeeId,
                        'updated_by' => $employeeId
                    ]);

                    Log::info('Merged tables without source invoices but with orders', [
                        'target_invoice_id' => $mergedInvoice->id,
                        'source_sessions' => $sourceTableSessionIds,
                        'new_total' => $newTotalAmount,
                        'new_final' => $finalAmount,
                        'total_paid' => $totalPaid,
                        'new_status' => $newStatus
                    ]);
                }
            } else {
                // Trường hợp: Tất cả bàn đều chưa có invoice
                // Chỉ gộp orders, không tạo invoice mới
                Log::info('Merged tables without any invoices - orders only', [
                    'source_sessions' => $sourceTableSessionIds,
                    'target_session' => $targetTableSessionId
                ]);
            }

            // 6. Cập nhật trạng thái các session nguồn
            TableSession::whereIn('id', $sourceTableSessionIds)
                ->update([
                    'status' => TableSession::STATUS_MERGED,
                    'merged_into_session_id' => $targetTableSessionId,
                    'ended_at' => now(),
                    'updated_by' => $employeeId,
                    'updated_at' => now()
                ]);

            // 7. Cập nhật target session
            $targetSession->update([
                'type' => TableSession::TYPE_MERGE,
                'status' => TableSession::STATUS_ACTIVE,
                'updated_by' => $employeeId
            ]);

            DB::commit();

            Log::info('Tables merged successfully', [
                'source_sessions' => $sourceTableSessionIds,
                'target_session' => $targetTableSessionId,
                'merged_invoice_id' => $mergedInvoice ? $mergedInvoice->id : null,
                'has_invoice' => !is_null($mergedInvoice),
                'employee_id' => $employeeId
            ]);

            return [
                'success' => true,
                'message' => 'Tables merged successfully',
                'data' => [
                    'merged_invoice' => $mergedInvoice ? $mergedInvoice->fresh()->load(['payments', 'invoicePromotions', 'tableSession']) : null,
                    'merged_from_sessions' => $sourceTableSessionIds,
                    'target_session' => $targetSession->fresh(),
                    'merge_type' => $mergedInvoice ? 'with_invoice' : 'orders_only'
                ]
            ];
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Merge tables failed', [
                'source_sessions' => $sourceTableSessionIds,
                'target_session' => $targetTableSessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to merge tables: ' . $e->getMessage(),
                'errors' => []
            ];
        }
    }

    /**
     * Tách hóa đơn theo tỷ lệ % của số tiền còn lại (Split Invoice)
     *
     * @param string $invoiceId ID hóa đơn cần tách
     * @param array $splits Mảng các phần tách [['percentage' => 40, 'note' => '...']]
     * @param string $employeeId ID nhân viên thực hiện
     * @return array
     */
    public function splitInvoice(string $invoiceId, array $splits, string $employeeId): array
    {
        DB::beginTransaction();

        try {
            // 1. Validate invoice
            $invoice = Invoice::with(['tableSession', 'invoicePromotions', 'payments'])->find($invoiceId);

            if (!$invoice) {
                return [
                    'success' => false,
                    'message' => 'Invoice not found',
                    'errors' => ['invoice_id' => ['Invoice does not exist']]
                ];
            }

            if (!$invoice->canBeSplit()) {
                return [
                    'success' => false,
                    'message' => 'Invoice cannot be split',
                    'errors' => ['invoice' => ['Invoice must be Unpaid or Partially Paid and not merged']]
                ];
            }

            // 2. Tính số tiền còn lại
            $totalPaid = $invoice->total_paid;
            $remainingAmount = $invoice->final_amount - $totalPaid;

            if ($remainingAmount <= 0) {
                return [
                    'success' => false,
                    'message' => 'Invoice fully paid, cannot split',
                    'errors' => ['invoice' => ['No remaining amount to split']]
                ];
            }

            // 3. Validate tổng % không vượt quá 100%
            $totalPercentage = collect($splits)->sum('percentage');
            if ($totalPercentage >= 100) {
                return [
                    'success' => false,
                    'message' => 'Total percentage must be less than 100%',
                    'errors' => ['splits' => ['Total split percentage cannot be 100% or more']]
                ];
            }

            // 4. Lưu giá trị gốc TRƯỚC KHI update
            $originalTotalAmount = $invoice->total_amount;
            $originalFinalAmount = $invoice->final_amount;

            // 5. Tạo các invoice con
            $childInvoices = [];
            $totalSplitFinal = 0;
            $totalSplitBase = 0;


            $discountPct = (float) ($invoice->discount ?? 0.0);
            $taxPct = (float) ($invoice->tax ?? 0.0);

            $multiplier = (1 - $discountPct / 100.0) * (1 + $taxPct / 100.0);

            if ($multiplier <= 0) {
                throw new Exception('Invalid invoice discount/tax values resulting in non-positive multiplier');
            }

            foreach ($splits as $split) {
                $percentage = $split['percentage'];
                $note = $split['note'] ?? null;

                // Tính số tiền tách (final_amount sau discount & tax) — dựa trên remaining final_amount
                $splitFinal = round($remainingAmount * ($percentage / 100.0), 2);

                // Tính ngược total_amount (trước discount & tax) bằng cách chia cho multiplier
                $splitTotal = round($splitFinal / $multiplier, 2);
                // For traceability compute discount and tax amounts on this split (not stored on invoice model)
                $splitDiscountAmount = round($splitTotal * ($discountPct / 100.0), 2);
                $splitTaxAmount = round($splitTotal * ($taxPct / 100.0), 2);

                // Tạo invoice con
                $childInvoice = Invoice::create([
                    'table_session_id' => $invoice->table_session_id,
                    'parent_invoice_id' => $invoice->id,
                    'total_amount' => $splitTotal,
                    'discount' => $invoice->discount,  // Giữ nguyên %
                    'tax' => $invoice->tax,            // Giữ nguyên %
                    'final_amount' => $splitFinal,
                    'status' => Invoice::STATUS_UNPAID,
                    'operation_type' => Invoice::OPERATION_SPLIT_INVOICE,
                    'split_percentage' => $percentage,
                    'operation_notes' => $note,
                    'operation_at' => now(),
                    'operation_by' => $employeeId,
                    'created_by' => $employeeId,
                    'updated_by' => $employeeId
                ]);

                $childInvoices[] = $childInvoice;
                $totalSplitFinal += $splitFinal;
                $totalSplitBase += $splitTotal;

                Log::info("Split invoice created", [
                    'child_invoice_id' => $childInvoice->id,
                    'percentage' => $percentage,
                    'split_amount' => $splitFinal
                ]);
            }

            // 6. Cập nhật invoice gốc
            $newTotalAmount = round($originalTotalAmount - $totalSplitBase, 2);
            $newFinalAmount = round($originalFinalAmount - $totalSplitFinal, 2);

            // Xác định trạng thái mới
            $newStatus = Invoice::STATUS_UNPAID;
            if ($totalPaid >= $newFinalAmount) {
                $newStatus = Invoice::STATUS_PAID;
            } elseif ($totalPaid > 0) {
                $newStatus = Invoice::STATUS_PARTIALLY_PAID;
            }

            $invoice->update([
                'total_amount' => $newTotalAmount,
                'final_amount' => $newFinalAmount,
                'status' => $newStatus,
                'updated_by' => $employeeId
            ]);

            // 7. Verify tổng với tolerance lớn hơn (do rounding)
            $verifyTotal = round($invoice->fresh()->total_amount + collect($childInvoices)->sum('total_amount'), 2);
            $difference = abs($verifyTotal - $originalTotalAmount);

            if ($difference > 0.10) { // Tăng tolerance lên 0.10 VND
                Log::error('Split verification failed - detailed info', [
                    'invoice_id' => $invoiceId,
                    'original_total' => $originalTotalAmount,
                    'parent_after_split' => $invoice->fresh()->total_amount,
                    'children_sum' => collect($childInvoices)->sum('total_amount'),
                    'verify_total' => $verifyTotal,
                    'difference' => $difference,
                    'splits' => $splits
                ]);
                throw new Exception(sprintf(
                    "Split verification failed: sum mismatch (difference: %.2f, original: %.2f, verify: %.2f)",
                    $difference,
                    $originalTotalAmount,
                    $verifyTotal
                ));
            }

            DB::commit();

            Log::info('Invoice split successfully', [
                'parent_invoice_id' => $invoiceId,
                'child_invoices' => collect($childInvoices)->pluck('id')->toArray(),
                'remaining_percentage' => 100 - $totalPercentage,
                'employee_id' => $employeeId
            ]);

            return [
                'success' => true,
                'message' => 'Invoice split successfully',
                'data' => [
                    'parent_invoice' => $invoice->fresh(),
                    'child_invoices' => $childInvoices,
                    'summary' => [
                        'original_remaining' => $remainingAmount,
                        'split_count' => count($childInvoices),
                        'total_split_percentage' => $totalPercentage,
                        'parent_remaining_percentage' => 100 - $totalPercentage,
                        'verification' => 'passed'
                    ]
                ]
            ];
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Split invoice failed', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to split invoice: ' . $e->getMessage(),
                'errors' => []
            ];
        }
    }

    /**
     * Tách bàn - Di chuyển món ăn từ bàn này sang bàn khác (Split Table)
     *
     * @param string $sourceSessionId ID session nguồn
     * @param array $orderItems Mảng các món cần tách [['order_item_id' => '...', 'quantity_to_transfer' => 2]]
     * @param string|null $targetSessionId ID session đích (nếu có)
     * @param string|null $targetDiningTableId ID bàn đích (nếu tạo mới)
     * @param string $employeeId ID nhân viên thực hiện
     * @param string|null $note Ghi chú
     * @return array
     */
    public function splitTable(
        string $sourceSessionId,
        array $orderItems,
        ?string $targetSessionId,
        ?string $targetDiningTableId,
        string $employeeId,
        ?string $note = null
    ): array {
        DB::beginTransaction();

        try {
            // 1. Validate source session
            $sourceSession = TableSession::find($sourceSessionId);
            if (!$sourceSession) {
                return [
                    'success' => false,
                    'message' => 'Source session not found',
                    'errors' => ['source_session' => ['Source session does not exist']]
                ];
            }

            // 2. Tính toán giá trị món tách
            $transferredItemIds = [];
            $transferredTotal = 0;
            $itemsToTransfer = [];

            foreach ($orderItems as $item) {
                $orderItem = OrderItem::with('order')->find($item['order_item_id']);

                if (!$orderItem) {
                    continue;
                }

                $qtyToTransfer = $item['quantity_to_transfer'];
                $unitPrice = $orderItem->total_price / $orderItem->quantity;
                $transferAmount = $unitPrice * $qtyToTransfer;

                $itemsToTransfer[] = [
                    'order_item' => $orderItem,
                    'quantity' => $qtyToTransfer,
                    'unit_price' => $unitPrice,
                    'transfer_amount' => $transferAmount
                ];

                $transferredTotal += $transferAmount;
                $transferredItemIds[] = $orderItem->id;
            }

            // 3. Kiểm tra source invoice (nếu có) - Validate remaining_amount
            $sourceInvoice = Invoice::where('table_session_id', $sourceSessionId)
                ->whereNull('merged_invoice_id')
                ->first();

            if ($sourceInvoice) {
                // Nếu có invoice, kiểm tra số tiền chuyển không vượt quá remaining
                $remainingAmount = $sourceInvoice->remaining_amount;
                if ($transferredTotal >= $remainingAmount) {
                    return [
                        'success' => false,
                        'message' => 'Cannot split: transferred amount must be less than remaining amount',
                        'errors' => ['amount' => [
                            sprintf(
                                'Transferred amount (%.2f) must be less than remaining (%.2f)',
                                $transferredTotal,
                                $remainingAmount
                            )
                        ]]
                    ];
                }
            }

            // 4. Lấy hoặc tạo target session
            if ($targetSessionId) {
                $targetSession = TableSession::find($targetSessionId);
                if (!$targetSession) {
                    return [
                        'success' => false,
                        'message' => 'Target session not found',
                        'errors' => ['target_session' => ['Target session does not exist']]
                    ];
                }
            } else {
                // Tạo session mới
                $targetSession = TableSession::create([
                    'type' => TableSession::TYPE_OFFLINE,
                    'status' => TableSession::STATUS_ACTIVE,
                    'started_at' => now(),
                    'customer_id' => $sourceSession->customer_id,
                    'created_by' => $employeeId,
                    'updated_by' => $employeeId
                ]);

                // Gán bàn cho session mới
                if ($targetDiningTableId) {
                    TableSessionDiningTable::create([
                        'table_session_id' => $targetSession->id,
                        'dining_table_id' => $targetDiningTableId,
                        'created_by' => $employeeId,
                        'updated_by' => $employeeId
                    ]);
                }
            }

            // 5. Lấy hoặc tạo order cho target session
            $targetOrder = Order::where('table_session_id', $targetSession->id)
                ->where('status', '!=', Order::STATUS_CANCELLED)
                ->first();

            if (!$targetOrder) {
                $targetOrder = Order::create([
                    'table_session_id' => $targetSession->id,
                    'status' => Order::STATUS_IN_PROGRESS,
                    'created_by' => $employeeId,
                    'updated_by' => $employeeId
                ]);
            }


            // 6. Di chuyển/Tách order items
            foreach ($itemsToTransfer as $item) {
                $orderItem = $item['order_item'];
                $qtyToTransfer = $item['quantity'];
                $unitPrice = $item['unit_price'];

                if ($qtyToTransfer >= $orderItem->quantity) {
                    // Chuyển toàn bộ
                    $orderItem->update([
                        'order_id' => $targetOrder->id,
                        'updated_by' => $employeeId
                    ]);
                } else {
                    // Tách một phần: Tạo item mới cho target (GIÁ GỐC)
                    OrderItem::create([
                        'order_id' => $targetOrder->id,
                        'dish_id' => $orderItem->dish_id,
                        'quantity' => $qtyToTransfer,
                        'price' => $unitPrice,
                        'total_price' => $unitPrice * $qtyToTransfer,
                        'notes' => $orderItem->notes,
                        'created_by' => $employeeId,
                        'updated_by' => $employeeId
                    ]);

                    // Giảm số lượng ở source
                    $newQty = $orderItem->quantity - $qtyToTransfer;
                    $orderItem->update([
                        'quantity' => $newQty,
                        'total_price' => $unitPrice * $newQty,
                        'updated_by' => $employeeId
                    ]);
                }
            }

            // Cập nhật total_amount cho source order
            $sourceOrder = $sourceInvoice?->order ?? Order::where('table_session_id', $sourceSession->id)->first();
            if ($sourceOrder) {
                $total = $sourceOrder->items()->sum('total_price');
                $sourceOrder->update([
                    'total_amount' => $total,
                    'updated_by' => $employeeId,
                ]);
            }

            // Cập nhật total_amount cho target order
            if ($targetOrder) {
                $total = $targetOrder->items()->sum('total_price');
                $targetOrder->update([
                    'total_amount' => $total,
                    'updated_by' => $employeeId,
                ]);
            }


            // 7. Cập nhật invoice (nếu có)
            if ($sourceInvoice) {
                // Cập nhật invoice bàn nguồn
                $sourceInvoice->update([
                    'total_amount' => $sourceInvoice->total_amount - $transferredTotal,
                    'final_amount' => ($sourceInvoice->total_amount - $transferredTotal)
                        * (1 - $sourceInvoice->discount / 100)
                        * (1 + $sourceInvoice->tax / 100),
                    'operation_type' => Invoice::OPERATION_SPLIT_TABLE,
                    'transferred_item_ids' => $transferredItemIds,
                    'operation_notes' => $note ?? "Split to session {$targetSession->id}",
                    'operation_at' => now(),
                    'operation_by' => $employeeId,
                    'updated_by' => $employeeId
                ]);

                // Kiểm tra target invoice
                $targetInvoice = Invoice::where('table_session_id', $targetSession->id)
                    ->whereNull('merged_invoice_id')
                    ->first();

                if ($targetInvoice) {
                    // Cập nhật invoice có sẵn (với weighted discount & tax)
                    $oldTotal = $targetInvoice->total_amount;
                    $newTotal = $oldTotal + $transferredTotal;

                    $weightedDiscount = 0;
                    $weightedTax = 0;

                    if ($newTotal > 0) {
                        $weightedDiscount = ($targetInvoice->discount * $oldTotal) / $newTotal;
                        $weightedTax = (
                            ($targetInvoice->tax * $oldTotal) +
                            (10 * $transferredTotal) // Default tax 10%
                        ) / $newTotal;
                    }

                    $targetInvoice->update([
                        'total_amount' => $newTotal,
                        'discount' => $weightedDiscount,
                        'tax' => $weightedTax,
                        'final_amount' => $newTotal * (1 - $weightedDiscount / 100) * (1 + $weightedTax / 100),
                        'updated_by' => $employeeId
                    ]);
                }
                // Nếu target chưa có invoice thì KHÔNG TẠO - chỉ chuyển order items
            }

            DB::commit();

            Log::info('Table split successfully', [
                'source_session' => $sourceSessionId,
                'target_session' => $targetSession->id,
                'transferred_total' => $transferredTotal,
                'items_count' => count($itemsToTransfer),
                'has_source_invoice' => $sourceInvoice ? true : false,
                'employee_id' => $employeeId,
                'customer_id' => $sourceSession->customer_id
            ]);

            // Lấy target invoice nếu có (sau khi commit)
            $targetInvoice = Invoice::where('table_session_id', $targetSession->id)
                ->whereNull('merged_invoice_id')
                ->first();

            return [
                'success' => true,
                'message' => 'Table split successfully',
                'data' => [
                    'source_session' => $sourceSession->fresh(),
                    'target_session' => $targetSession->fresh(),
                    'source_invoice' => $sourceInvoice ? $sourceInvoice->fresh() : null,
                    'target_invoice' => $targetInvoice,
                    'summary' => [
                        'transferred_amount' => $transferredTotal,
                        'items_transferred' => count($itemsToTransfer),
                        'source_remaining' => $sourceInvoice ? $sourceInvoice->fresh()->remaining_amount : null
                    ]
                ]
            ];
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Split table failed', [
                'source_session' => $sourceSessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to split table: ' . $e->getMessage(),
                'errors' => []
            ];
        }
    }

    /**
     * Validate điều kiện gộp bàn
     */
    private function validateMerge(array $sourceSessionIds, string $targetSessionId): array
    {
        // Kiểm tra target session
        $targetSession = TableSession::find($targetSessionId);
        if (!$targetSession) {
            return [
                'valid' => false,
                'message' => 'Target session not found',
                'errors' => ['target_session' => ['Target session does not exist']]
            ];
        }

        if (!$targetSession->canBeMerged()) {
            return [
                'valid' => false,
                'message' => 'Target session cannot be merged',
                'errors' => ['target_session' => ['Target session must be Active or Pending']]
            ];
        }

        // Kiểm tra source sessions
        $sourceSessions = TableSession::whereIn('id', $sourceSessionIds)->get();

        if ($sourceSessions->count() !== count($sourceSessionIds)) {
            return [
                'valid' => false,
                'message' => 'Some source sessions not found',
                'errors' => ['source_sessions' => ['One or more source sessions do not exist']]
            ];
        }

        foreach ($sourceSessions as $session) {
            if (!$session->canBeMerged()) {
                return [
                    'valid' => false,
                    'message' => "Session {$session->id} cannot be merged",
                    'errors' => ['source_sessions' => ["Session {$session->id} must be Active or Pending"]]
                ];
            }
        }

        // Kiểm tra invoices của source sessions
        $sourceInvoices = Invoice::whereIn('table_session_id', $sourceSessionIds)->get();
        foreach ($sourceInvoices as $invoice) {
            if (!$invoice->canBeMerged()) {
                return [
                    'valid' => false,
                    'message' => "Invoice {$invoice->id} cannot be merged",
                    'errors' => ['invoices' => ["Invoice {$invoice->id} must be Unpaid or Partially Paid"]]
                ];
            }
        }

        return [
            'valid' => true,
            'source_sessions' => $sourceSessions,
            'target_session' => $targetSession
        ];
    }

    /**
     * Lấy hoặc tạo invoice tổng cho target session
     */
    private function getOrCreateMergedInvoice(TableSession $targetSession, string $employeeId): Invoice
    {
        // Tìm invoice hiện có của target session
        $invoice = Invoice::where('table_session_id', $targetSession->id)
            ->whereNull('merged_invoice_id')
            ->first();

        if (!$invoice) {
            // Tạo invoice mới
            $invoice = Invoice::create([
                'table_session_id' => $targetSession->id,
                'total_amount' => 0,
                'discount' => 0,
                'tax' => 0,
                'final_amount' => 0,
                'status' => Invoice::STATUS_UNPAID,
                'created_by' => $employeeId,
                'updated_by' => $employeeId
            ]);
        }

        return $invoice;
    }

    /**
     * Tính toán lại invoice tổng từ các invoice nguồn
     */
    private function calculateMergedInvoice(Invoice $mergedInvoice, $sourceInvoices, string $employeeId): void
    {
        // Tổng tiền hàng
        $totalAmount = $mergedInvoice->total_amount;
        foreach ($sourceInvoices as $invoice) {
            $totalAmount += $invoice->total_amount;
        }

        // Tính weighted discount (theo tỷ trọng)
        $weightedDiscount = 0;
        if ($totalAmount > 0) {
            $discountSum = $mergedInvoice->discount * $mergedInvoice->total_amount;
            foreach ($sourceInvoices as $invoice) {
                $discountSum += ($invoice->discount * $invoice->total_amount);
            }
            $weightedDiscount = $discountSum / $totalAmount;
        }

        // Tính weighted tax
        $weightedTax = 0;
        if ($totalAmount > 0) {
            $taxSum = $mergedInvoice->tax * $mergedInvoice->total_amount;
            foreach ($sourceInvoices as $invoice) {
                $taxSum += ($invoice->tax * $invoice->total_amount);
            }
            $weightedTax = $taxSum / $totalAmount;
        }

        // Tính final amount
        $finalAmount = $totalAmount * (1 - $weightedDiscount / 100) * (1 + $weightedTax / 100);

        // Tính tổng đã thanh toán
        $totalPaid = Payment::where('invoice_id', $mergedInvoice->id)
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount');

        if ($finalAmount <= 0) {
            $status = Invoice::STATUS_UNPAID;
        } elseif ($totalPaid >= $finalAmount) {
            $status = Invoice::STATUS_PAID;
        } elseif ($totalPaid > 0) {
            $status = Invoice::STATUS_PARTIALLY_PAID;
        }

        // Cập nhật invoice
        $mergedInvoice->update([
            'total_amount' => $totalAmount,
            'discount' => $weightedDiscount,
            'tax' => $weightedTax,
            'final_amount' => $finalAmount,
            'status' => $status,
            'updated_by' => $employeeId
        ]);
    }

    /**
     * Sao chép promotions từ các invoice nguồn sang invoice tổng
     */
    private function copyPromotionsToMergedInvoice($sourceInvoices, Invoice $mergedInvoice, string $employeeId): void
    {
        foreach ($sourceInvoices as $invoice) {
            $promotions = InvoicePromotion::where('invoice_id', $invoice->id)->get();

            foreach ($promotions as $promotion) {
                // Kiểm tra xem promotion đã tồn tại chưa (tránh trùng)
                $exists = InvoicePromotion::where('invoice_id', $mergedInvoice->id)
                    ->where('promotion_id', $promotion->promotion_id)
                    ->exists();

                if (!$exists) {
                    InvoicePromotion::create([
                        'invoice_id' => $mergedInvoice->id,
                        'promotion_id' => $promotion->promotion_id,
                        'discount_value' => $promotion->discount_value,
                        'applied_at' => now(),
                        'created_by' => $employeeId,
                        'updated_by' => $employeeId
                    ]);
                }
            }
        }
    }

    /**
     * Hủy gộp bàn (rollback)
     *
     * @param string $mergedSessionId ID session đã gộp
     * @param string $employeeId ID nhân viên thực hiện
     * @return array
     */
    public function unmerge(string $mergedSessionId, string $employeeId): array
    {
        DB::beginTransaction();

        try {
            $mergedSession = TableSession::find($mergedSessionId);

            if (!$mergedSession || $mergedSession->type !== TableSession::TYPE_MERGE) {
                return [
                    'success' => false,
                    'message' => 'Not a merged session',
                    'errors' => ['session' => ['Session is not a merged session']]
                ];
            }

            // Tìm các session đã được gộp vào
            $sourceSessions = TableSession::where('merged_into_session_id', $mergedSessionId)
                ->where('status', TableSession::STATUS_MERGED)
                ->get();

            if ($sourceSessions->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No source sessions found',
                    'errors' => ['session' => ['No sessions were merged into this session']]
                ];
            }

            // Tìm invoice tổng
            $mergedInvoice = Invoice::where('table_session_id', $mergedSessionId)
                ->whereNull('merged_invoice_id')
                ->first();

            // Kiểm tra không được có payment nào đã completed
            if ($mergedInvoice && $mergedInvoice->total_paid > 0) {
                return [
                    'success' => false,
                    'message' => 'Cannot unmerge: payments already made',
                    'errors' => ['payment' => ['Merged invoice has completed payments']]
                ];
            }

            // Restore các invoice nguồn
            $sourceInvoices = Invoice::where('merged_invoice_id', $mergedInvoice->id)
                ->where('status', Invoice::STATUS_MERGED)
                ->get();

            foreach ($sourceInvoices as $invoice) {
                $invoice->update([
                    'status' => Invoice::STATUS_UNPAID,
                    'merged_invoice_id' => null,
                    'updated_by' => $employeeId
                ]);
            }

            // Restore các source sessions
            foreach ($sourceSessions as $session) {
                $session->update([
                    'status' => TableSession::STATUS_ACTIVE,
                    'merged_into_session_id' => null,
                    'ended_at' => null,
                    'updated_by' => $employeeId
                ]);

                // Di chuyển orders về session gốc
                $sessionOrders = Order::where('table_session_id', $mergedSessionId)
                    ->whereHas('items', function ($q) use ($sourceInvoices) {
                        // Logic phân bổ orders (cần custom)
                    });
            }

            // Xóa hoặc vô hiệu hóa merged invoice
            if ($mergedInvoice) {
                $mergedInvoice->update([
                    'status' => Invoice::STATUS_CANCELLED,
                    'updated_by' => $employeeId
                ]);
            }

            // Cập nhật merged session
            $mergedSession->update([
                'status' => TableSession::STATUS_CANCELLED,
                'updated_by' => $employeeId
            ]);

            DB::commit();

            Log::info('Unmerge successful', [
                'merged_session_id' => $mergedSessionId,
                'employee_id' => $employeeId
            ]);

            return [
                'success' => true,
                'message' => 'Tables unmerged successfully',
                'data' => [
                    'restored_sessions' => $sourceSessions->pluck('id')->toArray()
                ]
            ];
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Unmerge failed', [
                'merged_session_id' => $mergedSessionId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to unmerge: ' . $e->getMessage(),
                'errors' => []
            ];
        }
    }
}
