<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dining Table
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->integer('table_number');
            $table->integer('capacity');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Reservation
        Schema::create('reservations', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('customer_id', 10);
            $table->timestamp('reserved_at');
            $table->integer('number_of_people');
            $table->integer('status')->default(0); // 0=Pending,1=Confirmed...
            $table->string('notes', 200)->nullable();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->timestamps();
        });

        // Table Session
        Schema::create('table_sessions', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->integer('type')->default(0); // 0=Offline,1=Merge,...
            $table->integer('status')->default(0); // 0=Pending,1=Active...
            $table->string('parent_session_id', 10)->nullable();
            $table->string('merged_into_session_id', 10)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('customer_id', 10)->nullable();
            $table->string('employee_id', 10)->nullable();
            $table->foreign('parent_session_id')->references('id')->on('table_sessions')->nullOnDelete();
            $table->foreign('merged_into_session_id')->references('id')->on('table_sessions')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
            $table->timestamps();
        });

        // Pivot: session - reservation
        Schema::create('table_session_reservations', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('table_session_id', 10);
            $table->string('reservation_id', 10);
            $table->foreign('table_session_id')->references('id')->on('table_sessions')->cascadeOnDelete();
            $table->foreign('reservation_id')->references('id')->on('reservations')->cascadeOnDelete();
            $table->timestamps();
        });

        // Pivot: session - dining table
        Schema::create('table_session_dining_table', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('dining_table_id', 10);
            $table->string('table_session_id', 10);
            $table->foreign('dining_table_id')->references('id')->on('dining_tables')->cascadeOnDelete();
            $table->foreign('table_session_id')->references('id')->on('table_sessions')->cascadeOnDelete();
            $table->timestamps();
        });

        // Order
        Schema::create('orders', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('table_session_id', 10);
            $table->integer('status')->default(0); // 0=Open,1=InProgress...
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->foreign('table_session_id')->references('id')->on('table_sessions')->cascadeOnDelete();
            $table->timestamps();
        });

        // Dish Category
        Schema::create('dish_categories', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('name', 100);
            $table->string('desc', 255)->nullable();
            $table->timestamps();
        });

        // Dish
        Schema::create('dishes', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('name', 100);
            $table->decimal('price', 18, 2)->default(0);
            $table->string('desc', 255)->nullable();
            $table->string('category_id', 10);
            $table->integer('cooking_time')->default(0);
            $table->string('image', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreign('category_id')->references('id')->on('dish_categories')->cascadeOnDelete();
            $table->timestamps();
        });

        // Order Item
        Schema::create('order_items', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('order_id', 10);
            $table->string('dish_id', 10);
            $table->integer('quantity')->default(1);
            $table->decimal('price', 18, 2);
            $table->decimal('total_price', 18, 2);
            $table->integer('status')->default(0); // 0=Ordered,1=Cooking...
            $table->string('notes', 200)->nullable();
            $table->string('prepared_by', 10)->nullable();
            $table->dateTime('served_at')->nullable();
            $table->string('cancelled_reason', 200)->nullable();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('dish_id')->references('id')->on('dishes')->cascadeOnDelete();
            $table->foreign('prepared_by')->references('id')->on('employees')->nullOnDelete();
            $table->timestamps();
        });

        // Menu
        Schema::create('menus', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('name', 100);
            $table->string('description', 200)->nullable();
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        // Menu Item
        Schema::create('menu_items', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('menu_id', 10);
            $table->string('dish_id', 10);
            $table->decimal('price', 18, 2);
            $table->string('notes', 255)->nullable();
            $table->foreign('menu_id')->references('id')->on('menus')->cascadeOnDelete();
            $table->foreign('dish_id')->references('id')->on('dishes')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('dishes');
        Schema::dropIfExists('dish_categories');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('table_session_dining_table');
        Schema::dropIfExists('table_session_reservations');
        Schema::dropIfExists('table_sessions');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('dining_tables');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('customers');
    }
};
