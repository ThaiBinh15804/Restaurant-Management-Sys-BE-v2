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
        Schema::create('stock_exports', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->date('export_date');
            $table->string('purpose', 200)->nullable();
            $table->integer('status')->default(0);

            // Audit fields
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by', 10)->nullable();
            $table->string('updated_by', 10)->nullable();

            // Indexes
            $table->index('export_date', 'idx_stock_exports_date');
            $table->index('status', 'idx_stock_exports_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_exports');
    }
};
