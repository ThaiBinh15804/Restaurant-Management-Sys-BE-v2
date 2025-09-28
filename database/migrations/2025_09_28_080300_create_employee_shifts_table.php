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
        Schema::create('employee_shifts', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->date('assigned_date');
            $table->integer('status')->default(0);
            $table->timestamp('check_in')->nullable();
            $table->timestamp('check_out')->nullable();
            $table->integer('overtime_hours')->default(0);
            $table->string('notes', 255)->nullable();
            $table->string('employee_id', 10);
            $table->string('shift_id', 10);

            // Base model fields
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by', 10)->nullable();
            $table->string('updated_by', 10)->nullable();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('cascade');

            $table->unique(['employee_id', 'shift_id', 'assigned_date'], 'uq_employee_shift_assignment');
            $table->index('status', 'idx_employee_shifts_status');
            $table->index('assigned_date', 'idx_employee_shifts_assigned_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_shifts');
    }
};
