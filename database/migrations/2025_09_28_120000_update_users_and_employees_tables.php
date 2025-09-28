<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'name')) {
            DB::statement('ALTER TABLE users DROP COLUMN name');
        }

        if (Schema::hasColumn('employees', 'position')) {
            DB::statement('ALTER TABLE employees DROP COLUMN position');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'name')) {
                $table->string('name', 100)->nullable()->after('id');
            }
        });

        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'position')) {
                $table->string('position', 50)->nullable()->after('contract_type');
            }
        });
    }
};
