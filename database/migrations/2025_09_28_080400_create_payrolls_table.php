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
        Schema::create('payrolls', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->integer('month');
            $table->integer('year');
            $table->decimal('base_salary', 18, 2)->default(0);
            $table->decimal('bonus', 18, 2)->default(0);
            $table->decimal('deductions', 18, 2)->default(0);
            $table->decimal('final_salary', 18, 2)->default(0);
            $table->integer('status')->default(0);
            $table->integer('payment_method')->default(0);
            $table->string('payment_ref', 50)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('notes', 255)->nullable();
            $table->string('paid_by', 10)->nullable();
            $table->string('employee_id', 10);

            // Base model fields
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by', 10)->nullable();
            $table->string('updated_by', 10)->nullable();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('paid_by')->references('id')->on('employees')->onDelete('set null');

            $table->unique(['employee_id', 'month', 'year'], 'uq_payroll_employee_period');
            $table->index('status', 'idx_payrolls_status');
            $table->index(['year', 'month'], 'idx_payrolls_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
