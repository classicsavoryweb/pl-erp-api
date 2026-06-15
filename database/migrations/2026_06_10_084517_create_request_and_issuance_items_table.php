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
        Schema::create('request_and_issuance_items', function (Blueprint $table) {
            $table->id();
            $table->string('requested_number_id');
            $table->string('description');
            $table->string('size');
            $table->string('gender');
            $table->integer('stock_on_hand');
            $table->integer('requested_quantity');
            $table->integer('remaining_quantity');
            $table->string('uom');
            $table->decimal('unit_price')->default(0);
            $table->decimal('total_amount',12,2);
            $table->string('item_remarks')->nullable();
            $table->string('uniform_item_no');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_and_issuance_items');
    }
};
