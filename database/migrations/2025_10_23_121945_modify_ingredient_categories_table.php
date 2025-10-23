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
        Schema::table('ingredient_categories', function (Blueprint $table) {
            // Xóa foreign key
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
        });
    }

    public function down(): void
    {
        Schema::table('ingredient_categories', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('employees')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('employees')->onDelete('set null');
        });
    }
};
