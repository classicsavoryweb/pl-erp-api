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
        Schema::create('timekeeping_summary', function (Blueprint $table) {
            $table->id();
            $table->string("cut-off");
            $table->string("employee_id");
            $table->string("regular_days");
            $table->string("special_leave");
            $table->string("cancelled_off");
            $table->string("special_holiday");
            $table->string("legal_holiday");
            $table->string("regular_overtime");
            $table->string("night_differential");
            $table->string("night_differential_overtime");
            $table->string("legal_holiday_overtime");
            $table->string("special_holiday_overtime");
            $table->string("undertime");
            $table->string("tardiness")->nullable();
            $table->string("travel_time")->nullable();
            $table->string("adjustment_amount")->nullable();
            $table->string("remarks")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timekeeping_summary');
    }
};
