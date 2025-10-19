<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_promotions', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('invoice_id', 10);
            $table->string('promotion_id', 10);
            $table->timestamp('applied_at')->useCurrent();
            $table->decimal('discount_value', 18, 2)->default(0)->comment('Giá trị giảm giá thực tế cho báo cáo');
            
            $table->string('created_by', 10)->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('promotion_id')->references('id')->on('promotions')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index('applied_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_promotions');
    }
};