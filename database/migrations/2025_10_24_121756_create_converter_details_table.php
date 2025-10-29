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
        Schema::create('converter_details', function (Blueprint $table) {
            $table->id();
            $table->string("converter_main_id")->nullable();
            $table->string("employee_id");
            $table->string("branch_id");
            $table->string("transaction_type_id");
            $table->decimal("terms");
            $table->decimal("amount");
            $table->string("source");
            $table->string("status");
            $table->longText("remarks")->nullable();
            $table->string("added_by")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('converter_details');
    }
};
