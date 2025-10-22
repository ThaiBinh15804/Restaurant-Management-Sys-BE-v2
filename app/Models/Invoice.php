<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Invoice extends BaseModel
{
    // Status constants
    const STATUS_UNPAID = 0;
    const STATUS_PARTIALLY_PAID = 1;
    const STATUS_PAID = 2;
    const STATUS_CANCELLED = 3;
    const STATUS_MERGED = 4; // 🔥 Trạng thái hóa đơn đã được gộp

    // Operation Type constants - Phân loại thao tác
    const OPERATION_NORMAL = 'normal';
    const OPERATION_MERGE = 'merge';
    const OPERATION_SPLIT_INVOICE = 'split_invoice';
    const OPERATION_SPLIT_TABLE = 'split_table';

    protected $table = 'invoices';
    protected $idPrefix = 'IN'; // Ví dụ: IN0001, IN0002,...

    protected $fillable = [
        'table_session_id',
        'total_amount',
        'discount',
        'tax',
        'final_amount',
        'status',
        'parent_invoice_id',
        'merged_invoice_id',
        'operation_type',
        'source_invoice_ids',
        'split_percentage',
        'transferred_item_ids',
        'operation_notes',
        'operation_at',
        'operation_by',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'status' => 'integer',
        'source_invoice_ids' => 'array',
        'transferred_item_ids' => 'array',
        'split_percentage' => 'decimal:2',
        'operation_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope: chỉ lấy các hóa đơn đã thanh toán
     */
    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('status', 0);
    }

    /**
     * Scope: chỉ lấy các hóa đơn có thể gộp (Unpaid hoặc Partially Paid)
     */
    public function scopeMergeable($query)
    {
        return $query->whereIn('status', [self::STATUS_UNPAID, self::STATUS_PARTIALLY_PAID]);
    }

    /**
     * Quan hệ: Invoice cha (khi tách hóa đơn)
     */
    public function parentInvoice()
    {
        return $this->belongsTo(Invoice::class, 'parent_invoice_id');
    }

    /**
     * Quan hệ: Các invoice con (được tách từ invoice này)
     */
    public function childInvoices()
    {
        return $this->hasMany(Invoice::class, 'parent_invoice_id');
    }

    /**
     * Quan hệ: Invoice tổng (khi được gộp vào)
     */
    public function mergedInvoice()
    {
        return $this->belongsTo(Invoice::class, 'merged_invoice_id');
    }

    /**
     * Quan hệ: Các invoice đã được gộp vào invoice này
     */
    public function mergedFromInvoices()
    {
        return $this->hasMany(Invoice::class, 'merged_invoice_id');
    }

    /**
     * Quan hệ: Invoice có thể có nhiều payment
     */
    public function payments()
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }

    /**
     * Quan hệ: Invoice có thể có nhiều khuyến mãi áp dụng
     */
    public function invoicePromotions()
    {
        return $this->hasMany(InvoicePromotion::class, 'invoice_id');
    }

    /**
     * (Tùy chọn) Quan hệ với TableSession nếu bạn dùng bảng đó
     */

    public function tableSession()
    {
        return $this->belongsTo(TableSession::class, 'table_session_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Kiểm tra trạng thái thanh toán dạng text
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_UNPAID => 'Unpaid',
            self::STATUS_PARTIALLY_PAID => 'Partially Paid',
            self::STATUS_PAID => 'Paid',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_MERGED => 'Merged',
            default => 'Unknown',
        };
    }

    /**
     * Kiểm tra loại thao tác dạng text
     */
    public function getOperationTypeLabelAttribute(): string
    {
        return match ($this->operation_type) {
            self::OPERATION_MERGE => 'Gộp bàn',
            self::OPERATION_SPLIT_INVOICE => 'Tách hóa đơn theo %',
            self::OPERATION_SPLIT_TABLE => 'Tách bàn',
            self::OPERATION_NORMAL => 'Bình thường',
            default => 'Không xác định',
        };
    }

    /**
     * Kiểm tra xem invoice có thể gộp không
     */
    public function canBeMerged(): bool
    {
        return in_array($this->status, [self::STATUS_UNPAID, self::STATUS_PARTIALLY_PAID]);
    }

    /**
     * Kiểm tra xem invoice có thể tách không
     */
    public function canBeSplit(): bool
    {
        return in_array($this->status, [self::STATUS_UNPAID, self::STATUS_PARTIALLY_PAID])
            && is_null($this->merged_invoice_id);
    }

    /**
     * Tính tổng số tiền đã thanh toán
     */
    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount');
    }

    /**
     * Tính số tiền còn phải trả
     */
    public function getRemainingAmountAttribute(): float
    {
        return max(0, $this->final_amount - $this->total_paid);
    }

    /**
     * Lấy danh sách invoice nguồn (dùng cho merge)
     * Trả về Collection các Invoice đã được gộp vào invoice này
     */
    public function getSourceInvoicesAttribute()
    {
        if (empty($this->source_invoice_ids)) {
            return collect([]);
        }

        return Invoice::whereIn('id', $this->source_invoice_ids)->get();
    }

    /**
     * Lấy thông tin audit trail đầy đủ
     */
    public function getAuditTrailAttribute(): array
    {
        $trail = [
            'operation_type' => $this->operation_type_label,
            'operation_at' => $this->operation_at?->format('Y-m-d H:i:s'),
            'operation_by' => $this->operation_by,
            'notes' => $this->operation_notes,
        ];

        // Thông tin về invoice cha/con
        if ($this->parent_invoice_id) {
            $trail['parent_invoice'] = $this->parentInvoice?->id ?? null;
        }

        if ($this->merged_invoice_id) {
            $trail['merged_into'] = $this->mergedInvoice?->id ?? null;
        }

        switch ($this->operation_type) {
            case self::OPERATION_MERGE:
                $trail['source_invoices'] = $this->source_invoice_ids ?? [];
                $trail['source_count'] = count($this->source_invoice_ids ?? []);
                break;

            case self::OPERATION_SPLIT_INVOICE:
                $trail['split_percentage'] = $this->split_percentage . '%';
                $trail['parent_invoice'] = $this->parent_invoice_id;
                break;

            case self::OPERATION_SPLIT_TABLE:
                $trail['transferred_items'] = $this->transferred_item_ids ?? [];
                $trail['items_count'] = count($this->transferred_item_ids ?? []);
                break;
        }

        return $trail;
    }

    /**
     * Scope: Lọc theo loại thao tác
     */
    public function scopeByOperationType($query, string $operationType)
    {
        return $query->where('operation_type', $operationType);
    }

    /**
     * Scope: Lấy các invoice được tạo từ merge
     */
    public function scopeMergedInvoices($query)
    {
        return $query->where('operation_type', self::OPERATION_MERGE);
    }

    /**
     * Scope: Lấy các invoice được tạo từ split
     */
    public function scopeSplitInvoices($query)
    {
        return $query->whereIn('operation_type', [
            self::OPERATION_SPLIT_INVOICE,
            self::OPERATION_SPLIT_TABLE
        ]);
    }

    /**
     * Scope: Lấy audit trail trong khoảng thời gian
     */
    public function scopeOperationBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('operation_at', [$startDate, $endDate]);
    }
}
