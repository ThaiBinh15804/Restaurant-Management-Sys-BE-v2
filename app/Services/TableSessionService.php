<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePromotion;
use App\Models\Order;
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

            // 2. Thu thập tất cả invoices từ các session nguồn
            $sourceInvoices = Invoice::whereIn('table_session_id', $sourceTableSessionIds)
                ->mergeable()
                ->get();

            // 3. Tạo hoặc lấy invoice tổng của target session
            $mergedInvoice = $this->getOrCreateMergedInvoice($targetSession, $employeeId);

            // 4. Tính toán lại invoice tổng
            $this->calculateMergedInvoice($mergedInvoice, $sourceInvoices, $employeeId);

            // 5. Chuyển tất cả orders sang target session
            Order::whereIn('table_session_id', $sourceTableSessionIds)
                ->update([
                    'table_session_id' => $targetTableSessionId,
                    'updated_by' => $employeeId,
                    'updated_at' => now()
                ]);

            // 6. Cập nhật trạng thái các invoice nguồn
            foreach ($sourceInvoices as $invoice) {
                $invoice->update([
                    'status' => Invoice::STATUS_MERGED,
                    'merged_invoice_id' => $mergedInvoice->id,
                    'updated_by' => $employeeId
                ]);
            }

            // 7. Chuyển các payment đã hoàn thành sang invoice tổng
            Payment::whereIn('invoice_id', $sourceInvoices->pluck('id'))
                ->where('status', Payment::STATUS_COMPLETED)
                ->update([
                    'invoice_id' => $mergedInvoice->id,
                    'updated_by' => $employeeId,
                    'updated_at' => now()
                ]);

            // 8. Sao chép các promotion từ invoice nguồn
            $this->copyPromotionsToMergedInvoice($sourceInvoices, $mergedInvoice, $employeeId);

            // 9. Cập nhật trạng thái các session nguồn
            TableSession::whereIn('id', $sourceTableSessionIds)
                ->update([
                    'status' => TableSession::STATUS_MERGED,
                    'merged_into_session_id' => $targetTableSessionId,
                    'ended_at' => now(),
                    'updated_by' => $employeeId,
                    'updated_at' => now()
                ]);

            // 10. Cập nhật target session
            $targetSession->update([
                'type' => TableSession::TYPE_MERGE,
                'status' => TableSession::STATUS_ACTIVE,
                'updated_by' => $employeeId
            ]);

            DB::commit();

            Log::info('Tables merged successfully', [
                'source_sessions' => $sourceTableSessionIds,
                'target_session' => $targetTableSessionId,
                'merged_invoice_id' => $mergedInvoice->id,
                'employee_id' => $employeeId
            ]);

            return [
                'success' => true,
                'message' => 'Tables merged successfully',
                'data' => [
                    'merged_invoice' => $mergedInvoice->load(['payments', 'invoicePromotions', 'tableSession']),
                    'merged_from_sessions' => $sourceTableSessionIds,
                    'target_session' => $targetSession->fresh()
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
     * Tách hóa đơn thành nhiều hóa đơn con
     * 
     * @param string $invoiceId ID hóa đơn cần tách
     * @param array $splits Mảng các phần tách [['order_item_ids' => [...], 'note' => '...']]
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

            // 2. Tạo các invoice con
            $childInvoices = [];
            $totalSplitAmount = 0;

            foreach ($splits as $split) {
                $childInvoice = $this->createSplitInvoice($invoice, $split['order_item_ids'], $employeeId);
                $childInvoices[] = $childInvoice;
                $totalSplitAmount += $childInvoice->final_amount;
            }

            // 3. Cập nhật invoice gốc
            $remainingAmount = $invoice->final_amount - $totalSplitAmount;
            
            if ($remainingAmount < 0) {
                throw new Exception('Split amounts exceed original invoice total');
            }

            // Nếu tách hết, đánh dấu invoice gốc là completed
            if ($remainingAmount == 0) {
                $invoice->update([
                    'status' => Invoice::STATUS_PAID, // Đã tách hết
                    'updated_by' => $employeeId
                ]);
            } else {
                // Cập nhật lại số tiền invoice gốc
                $invoice->update([
                    'total_amount' => $remainingAmount,
                    'final_amount' => $remainingAmount,
                    'updated_by' => $employeeId
                ]);
            }

            DB::commit();

            Log::info('Invoice split successfully', [
                'parent_invoice_id' => $invoiceId,
                'child_invoices' => collect($childInvoices)->pluck('id')->toArray(),
                'employee_id' => $employeeId
            ]);

            return [
                'success' => true,
                'message' => 'Invoice split successfully',
                'data' => [
                    'parent_invoice' => $invoice->fresh(),
                    'child_invoices' => $childInvoices
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

        // Xác định trạng thái
        $status = Invoice::STATUS_UNPAID;
        if ($totalPaid >= $finalAmount) {
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
     * Tạo invoice con khi tách
     */
    private function createSplitInvoice(Invoice $parentInvoice, array $orderItemIds, string $employeeId): Invoice
    {
        // Tính tổng giá trị các order items
        $orderItems = DB::table('order_items')
            ->whereIn('id', $orderItemIds)
            ->get();

        $subtotal = $orderItems->sum('total_price');

        // Áp dụng cùng tỷ lệ discount và tax như invoice gốc
        $discount = $parentInvoice->discount;
        $tax = $parentInvoice->tax;
        $finalAmount = $subtotal * (1 - $discount / 100) * (1 + $tax / 100);

        // Tạo invoice con
        $childInvoice = Invoice::create([
            'table_session_id' => $parentInvoice->table_session_id,
            'parent_invoice_id' => $parentInvoice->id,
            'total_amount' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'final_amount' => $finalAmount,
            'status' => Invoice::STATUS_UNPAID,
            'created_by' => $employeeId,
            'updated_by' => $employeeId
        ]);

        // TODO: Cập nhật order_items để liên kết với invoice con
        // (Cần thêm trường invoice_id vào bảng order_items nếu cần tracking chính xác)

        return $childInvoice;
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
