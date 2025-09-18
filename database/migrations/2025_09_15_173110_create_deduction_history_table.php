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
        Schema::create('deduction_history', function (Blueprint $table) {
            $table->id();
            $table->string("employee_id")->nullable();
            $table->string("reference_doc")->nullable();
            $table->string("deduction_id")->nullable();
            $table->string("amount")->nullable();
            $table->string("pay_date")->nullable();
            $table->string("date_time")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deduction_history');
    }
};
