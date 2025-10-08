<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('customer_id', 10);
            $table->timestamp('reserved_at');
            $table->integer('number_of_people');
            $table->integer('status'); // 0=Pending, 1=Confirmed, 2=Cancelled, 3=Completed
            $table->string('notes', 200)->nullable();
            $table->timestamps();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};