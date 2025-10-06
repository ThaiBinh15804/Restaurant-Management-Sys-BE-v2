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
        Schema::table('shifts', function (Blueprint $table) {
            $table->date('shift_date')->after('name')->nullable();
        });

        Schema::table('employee_shifts', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['shift_id']);
            $table->dropUnique('uq_employee_shift_assignment');
            $table->dropIndex('idx_employee_shifts_assigned_date');
        });

        Schema::table('employee_shifts', function (Blueprint $table) {
            $table->dropColumn('assigned_date');
        });

        Schema::table('employee_shifts', function (Blueprint $table) {
            $table->unique(['employee_id', 'shift_id'], 'uq_employee_shift_assignment');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('shift_id')->references('id')->on('shifts')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn('shift_date');
        });

        Schema::table('employee_shifts', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['shift_id']);
            $table->dropUnique('uq_employee_shift_assignment');
        });

        Schema::table('employee_shifts', function (Blueprint $table) {
            $table->date('assigned_date')->after('id')->nullable();
        });

        Schema::table('employee_shifts', function (Blueprint $table) {
            $table->unique(['employee_id', 'shift_id', 'assigned_date'], 'uq_employee_shift_assignment');
            $table->index('assigned_date', 'idx_employee_shifts_assigned_date');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('shift_id')->references('id')->on('shifts')->cascadeOnDelete();
        });
    }
};
