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
        Schema::create('customers', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('full_name', 100);
            $table->string('phone', 15)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('address', 200)->nullable();
            $table->integer('membership_level')->default(0);
            $table->string('user_id', 10)->nullable()->unique();

            // Base model fields
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by', 10)->nullable();
            $table->string('updated_by', 10)->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('membership_level', 'idx_customers_membership_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
