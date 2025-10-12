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
            $table->integer('usage_limit')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by', 10)->nullable();
            $table->string('updated_by', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
