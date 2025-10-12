<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('table_session_id', 10)->nullable(); // foreign key ngoài phạm vi hiện tại
            $table->decimal('total_amount', 18, 2);
            $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('final_amount', 18, 2);
            $table->integer('status')->default(0); // 0=Unpaid, 1=Partially Paid, 2=Paid, 3=Cancelled

            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('created_by', 10)->nullable();
            $table->string('updated_by', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
