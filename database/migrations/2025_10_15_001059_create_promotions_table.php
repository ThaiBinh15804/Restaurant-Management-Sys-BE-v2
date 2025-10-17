<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('code', 20)->unique();
            $table->string('description', 200)->nullable();
            $table->decimal('discount_percent', 18, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('usage_limit')->default(0); // 0 = không giới hạn
            $table->integer('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'start_date', 'end_date']);
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};