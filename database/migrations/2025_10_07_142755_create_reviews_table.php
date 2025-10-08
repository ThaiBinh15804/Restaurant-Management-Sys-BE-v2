<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->integer('target_type'); // 0=Service, 1=Dish, 2=Employee
            $table->string('target_id', 10);
            $table->integer('rating');
            $table->boolean('is_display')->default(true);
            $table->string('comment', 500)->nullable();
            $table->boolean('is_public')->default(true);
            $table->string('customer_id', 10);
            $table->timestamps(); 
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
