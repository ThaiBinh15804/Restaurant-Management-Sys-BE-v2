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
        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('id')->change();
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropPrimary(['id']);
            $table->dropColumn('id');
            
            // Add new custom id as primary key
            $table->string('id', 10)->primary()->first();
            
            // Modify existing email column
            $table->string('email', 100)->change();
            
            // Add new columns
            $table->integer('status')->default(1)->after('password'); // 1 = Active
            $table->string('avatar', 255)->nullable()->after('status');
            $table->string('role_id', 10)->nullable()->after('avatar');
            
            // Add audit fields
            $table->string('created_by', 10)->nullable()->after('updated_at');
            $table->string('updated_by', 10)->nullable()->after('created_by');
            
            // Add foreign key
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop foreign key and indexes
            $table->dropForeign(['role_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['role_id']);
            
            // Drop added columns
            $table->dropColumn(['status', 'avatar', 'role_id', 'created_by', 'updated_by']);
            
            // Restore original id column
            $table->dropPrimary(['id']);
            $table->dropColumn('id');
            $table->id()->first();
            
            // Restore original email column
            $table->string('email')->unique()->change();
        });
    }
};
