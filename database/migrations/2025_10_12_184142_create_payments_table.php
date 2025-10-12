<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->decimal('amount', 18, 2);
            $table->integer('method'); // Enum: 0=Cash, 1=Bank_transfer
            $table->integer('status')->default(0); // Enum: 0=Pending, 1=Completed, 2=Failed, 3=Refunded
            $table->timestamp('paid_at')->nullable();
            $table->string('invoice_id', 10);
            $table->string('employee_id', 10)->nullable(); // để ON DELETE SET NULL không lỗi
            $table->string('desc_issue', 225)->nullable();

            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('set null');

            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by', 10)->nullable();
            $table->string('updated_by', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
