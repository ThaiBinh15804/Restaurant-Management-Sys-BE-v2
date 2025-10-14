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
        Schema::create('stock_export_details', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->decimal('quantity', 18, 2);
            $table->string('ingredient_id', 10);
            $table->string('stock_export_id', 10);

            // Audit fields
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by', 10)->nullable();
            $table->string('updated_by', 10)->nullable();

            // Foreign keys
            $table->foreign('stock_export_id')
                  ->references('id')
                  ->on('stock_exports')
                  ->onDelete('cascade');

            $table->foreign('ingredient_id')
                  ->references('id')
                  ->on('ingredients')
                  ->onDelete('restrict');

            // Indexes
            $table->index('stock_export_id', 'idx_stock_export_details_export');
            $table->index('ingredient_id', 'idx_stock_export_details_ingredient');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_export_details');
    }
};
