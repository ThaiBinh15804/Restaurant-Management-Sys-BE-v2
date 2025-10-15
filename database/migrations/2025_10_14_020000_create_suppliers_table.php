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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('name', 100);
            $table->string('phone', 20)->nullable();
            $table->string('contact_person_name', 100)->nullable();
            $table->string('contact_person_phone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('address', 200)->nullable();
            $table->boolean('is_active')->default(true);

            // Audit fields
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by', 10)->nullable();
            $table->string('updated_by', 10)->nullable();

            // Indexes
            $table->index('is_active', 'idx_suppliers_is_active');
            $table->index('name', 'idx_suppliers_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
