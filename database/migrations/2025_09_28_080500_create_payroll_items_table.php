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
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->string('id', 20)->primary();
            $table->string('payroll_id', 10);
            $table->integer('item_type')->default(0);
            $table->string('code', 50);
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 18, 2)->default(0);

            // Base model fields
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by', 10)->nullable();
            $table->string('updated_by', 10)->nullable();

            $table->foreign('payroll_id')->references('id')->on('payrolls')->onDelete('cascade');
            $table->index('item_type', 'idx_payroll_items_item_type');
            $table->index('code', 'idx_payroll_items_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
