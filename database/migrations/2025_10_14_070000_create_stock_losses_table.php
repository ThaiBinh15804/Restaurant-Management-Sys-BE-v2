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
        Schema::create('stock_losses', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->decimal('quantity', 18, 2);
            $table->string('reason', 200)->nullable();
            $table->date('loss_date');
            $table->string('employee_id', 10)->nullable();
            $table->string('ingredient_id', 10);

            // Audit fields
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by', 10)->nullable();
            $table->string('updated_by', 10)->nullable();

            // Foreign keys
            $table->foreign('employee_id')
                  ->references('id')
                  ->on('employees')
                  ->onDelete('set null');

            $table->foreign('ingredient_id')
                  ->references('id')
                  ->on('ingredients')
                  ->onDelete('restrict');

            // Indexes
            $table->index('loss_date', 'idx_stock_losses_date');
            $table->index('employee_id', 'idx_stock_losses_employee');
            $table->index('ingredient_id', 'idx_stock_losses_ingredient');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_losses');
    }
};
