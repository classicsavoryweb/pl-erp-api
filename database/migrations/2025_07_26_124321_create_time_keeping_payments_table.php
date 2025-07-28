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
        Schema::create('time_keeping_payments', function (Blueprint $table) {
            $table->id();
            $table->string("timekeeping_id");
            $table->string("base_amount")->nullable();
            $table->string("tardiness_deduction")->nullable();
            $table->string("undertime_deduction")->nullable();
            $table->string("total_amount")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_keeping_payments');
    }
};
