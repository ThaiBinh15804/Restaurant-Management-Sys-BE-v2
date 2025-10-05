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
        Schema::create('employees', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('full_name', 100);
            $table->string('phone', 15)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('address', 200)->nullable();
            $table->string('bank_account', 100)->nullable();
            $table->integer('contract_type')->default(0);
            $table->string('position', 50)->nullable();
            $table->decimal('base_salary', 18, 2)->default(0);
            $table->date('hire_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('user_id', 10)->nullable()->unique();

            // Base model fields
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by', 10)->nullable();
            $table->string('updated_by', 10)->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('is_active', 'idx_employees_is_active');
            $table->index('contract_type', 'idx_employees_contract_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
