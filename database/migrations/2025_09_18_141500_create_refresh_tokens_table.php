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
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('user_id', 10);
            $table->string('token', 255);
            $table->timestamp('expire_at');
            $table->integer('status')->default(2); // 1 = Expired, 2 = Active, 3 = Revoked
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_by', 10)->nullable();
            $table->string('ip_address', 45)->nullable(); // IPv4 and IPv6 compatible
            $table->string('user_agent', 255)->nullable();
            
            // Base model fields
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by', 10)->nullable();
            $table->string('updated_by', 10)->nullable();
            
            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('revoked_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index(['user_id']);
            $table->index(['status']);
            $table->index(['expire_at']);
            $table->index(['token']); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
