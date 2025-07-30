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
            $table->string("timekeeping_id")->nullable();
            $table->string("adjustment_timekeeping_id")->nullable();
            $table->string("base_amount")->nullable();
            $table->string("tardiness_deduction")->nullable();
            $table->string("undertime_deduction")->nullable();
            $table->string("cancelled_off")->nullable();
            $table->string("regular_overtime")->nullable();
            $table->string("night_differential")->nullable();
            $table->string("night_differential_overtime")->nullable();
            $table->string("legal_holiday")->nullable();
            $table->string("special_holiday")->nullable();
            $table->string("legal_holiday_overtime")->nullable();
            $table->string("special_holiday_overtime")->nullable();
            $table->string("total_amount")->nullable();
            $table->string("type")->nullable();
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
