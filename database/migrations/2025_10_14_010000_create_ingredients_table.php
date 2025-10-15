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
        Schema::create('ingredients', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('name', 100);
            $table->string('unit', 20);
            $table->decimal('current_stock', 18, 2)->default(0);
            $table->decimal('min_stock', 18, 2)->default(0);
            $table->decimal('max_stock', 18, 2)->nullable();
            $table->boolean('is_active')->default(true);

            // Audit fields
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by', 10)->nullable();
            $table->string('updated_by', 10)->nullable();

            // Indexes
            $table->index('is_active', 'idx_ingredients_is_active');
            $table->index('name', 'idx_ingredients_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
