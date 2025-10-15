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
            // Trường cho tính năng tách hóa đơn (split invoice)
            $table->string('parent_invoice_id', 10)->nullable()->after('status');
            
            // Trường cho tính năng gộp hóa đơn (merge invoice)
            $table->string('merged_invoice_id', 10)->nullable()->after('parent_invoice_id');
            
            // Foreign key constraints (nếu cần)
            $table->foreign('parent_invoice_id')->references('id')->on('invoices')->onDelete('set null');
            $table->foreign('merged_invoice_id')->references('id')->on('invoices')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Xóa foreign keys trước (nếu có)
            $table->dropForeign(['parent_invoice_id']);
            $table->dropForeign(['merged_invoice_id']);
            
            // Xóa các trường
            $table->dropColumn(['parent_invoice_id', 'merged_invoice_id']);
        });
    }
};
