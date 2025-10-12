<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_promotions', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->timestamp('applied_at')->nullable();
            $table->decimal('discount_value', 18, 2)->default(0);
            $table->string('promotion_id', 10);
            $table->string('invoice_id', 10);

            $table->foreign('promotion_id')->references('id')->on('promotions')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');

            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by', 10)->nullable();
            $table->string('updated_by', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_promotions');
    }
};
