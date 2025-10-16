<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Loại thao tác: merge, split_invoice, split_table, normal
            $table->string('operation_type', 20)->nullable()->after('status');
            
            // Lưu danh sách invoice nguồn (dùng cho merge)
            $table->json('source_invoice_ids')->nullable()->after('operation_type');
            
            // Tỷ lệ % cho split invoice
            $table->decimal('split_percentage', 5, 2)->nullable()->after('source_invoice_ids');
            
            // Danh sách order_item_ids cho split table
            $table->json('transferred_item_ids')->nullable()->after('split_percentage');
            
            // Ghi chú thao tác
            $table->text('operation_notes')->nullable()->after('transferred_item_ids');
            
            // Thời điểm thao tác
            $table->timestamp('operation_at')->nullable()->after('operation_notes');
            
            // Nhân viên thực hiện thao tác
            $table->string('operation_by', 20)->nullable()->after('operation_at');
            
            // Index để truy vấn nhanh
            $table->index('operation_type', 'idx_invoices_operation_type');
            $table->index('operation_at', 'idx_invoices_operation_at');
            $table->index(['parent_invoice_id', 'operation_type'], 'idx_invoices_parent_operation');
            $table->index(['merged_invoice_id', 'operation_type'], 'idx_invoices_merged_operation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex('idx_invoices_operation_type');
            $table->dropIndex('idx_invoices_operation_at');
            $table->dropIndex('idx_invoices_parent_operation');
            $table->dropIndex('idx_invoices_merged_operation');
            
            // Drop columns
            $table->dropColumn([
                'operation_type',
                'source_invoice_ids',
                'split_percentage',
                'transferred_item_ids',
                'operation_notes',
                'operation_at',
                'operation_by'
            ]);
        });
    }
};
