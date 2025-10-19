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
        Schema::create('dish_ingredient', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('dish_id', 10);
            $table->string('ingredient_id', 10);
            $table->decimal('quantity', 18, 2)->comment('Lượng nguyên liệu cần cho 1 phần món ăn');
            $table->string('note', 255)->nullable()->comment('Ghi chú tùy chọn');

            // Khóa ngoại
            $table->foreign('dish_id')
                ->references('id')
                ->on('dishes')
                ->cascadeOnDelete();

            $table->foreign('ingredient_id')
                ->references('id')
                ->on('ingredients')
                ->cascadeOnDelete();

            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by', 10)->nullable();
            $table->string('updated_by', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dish_ingredient');
    }
};
