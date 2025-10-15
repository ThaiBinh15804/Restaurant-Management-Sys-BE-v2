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
        Schema::create('stock_imports', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->date('import_date');
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->string('supplier_id', 10)->nullable();

            // Audit fields
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by', 10)->nullable();
            $table->string('updated_by', 10)->nullable();

            // Foreign keys
            $table->foreign('supplier_id')
                  ->references('id')
                  ->on('suppliers')
                  ->onDelete('set null');

            // Indexes
            $table->index('import_date', 'idx_stock_imports_date');
            $table->index('supplier_id', 'idx_stock_imports_supplier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_imports');
    }
};
