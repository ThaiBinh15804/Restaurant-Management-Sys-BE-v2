<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->string('id', 20)->primary();
            $table->string('event_code', 50);
            $table->string('title', 200);
            $table->string('message', 1000);
            $table->integer('type'); // 0=System, 1=Order, 2=Stock, 3=Payroll
            $table->integer('priority'); // 0=Low, 1=Normal, 2=High
            $table->integer('channel'); // 0=InApp, 1=Email, 2=SMS, 3=Multi
            $table->integer('status'); // 0=Pending, 1=Sent, 2=Failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};