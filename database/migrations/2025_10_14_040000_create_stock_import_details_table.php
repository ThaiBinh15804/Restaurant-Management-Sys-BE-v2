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
        Schema::create('stock_import_details', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->decimal('ordered_quantity', 18, 2);
            $table->decimal('received_quantity', 18, 2);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('total_price', 18, 2);
            $table->string('stock_import_id', 10);
            $table->string('ingredient_id', 10);

            // Audit fields
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by', 10)->nullable();
            $table->string('updated_by', 10)->nullable();

            // Foreign keys
            $table->foreign('stock_import_id')
                  ->references('id')
                  ->on('stock_imports')
                  ->onDelete('cascade');

            $table->foreign('ingredient_id')
                  ->references('id')
                  ->on('ingredients')
                  ->onDelete('restrict');

            // Indexes
            $table->index('stock_import_id', 'idx_stock_import_details_import');
            $table->index('ingredient_id', 'idx_stock_import_details_ingredient');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_import_details');
    }
};
