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
        Schema::table('ingredients', function (Blueprint $table) {
            $table->string('ingredient_category_id', 10)->nullable()->after('id');
            
            $table->foreign('ingredient_category_id')
                  ->references('id')
                  ->on('ingredient_categories')
                  ->onDelete('restrict');
                  
            $table->index('ingredient_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropForeign(['ingredient_category_id']);
            $table->dropIndex(['ingredient_category_id']);
            $table->dropColumn('ingredient_category_id');
        });
    }
};
