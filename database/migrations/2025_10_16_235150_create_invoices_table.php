<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('table_session_id', 10)->unique();
            $table->decimal('total_amount', 18, 2)->default(0); // Tổng trước giảm giá và thuế
            $table->decimal('discount', 18, 2)->default(0); // % giảm giá
            $table->decimal('tax', 18, 2)->default(0); // % thuế
            $table->decimal('final_amount', 18, 2)->default(0); // Tổng cuối cùng
            $table->tinyInteger('status')->default(0)->comment('0=Unpaid, 1=Partially Paid, 2=Paid, 3=Cancelled');
            
            $table->string('created_by', 10)->nullable();
            $table->string('updated_by', 10)->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('table_session_id')->references('id')->on('table_sessions')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};