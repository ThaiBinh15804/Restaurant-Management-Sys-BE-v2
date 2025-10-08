<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dishes', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('name', 10);
            $table->decimal('price', 18, 2);
            $table->string('desc', 255)->nullable();
            $table->string('category_id', 10);
            $table->integer('cooking_time')->nullable();
            $table->string('image', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('category_id')->references('id')->on('dish_categories')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dishes');
    }
};