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
        Schema::create('adjustments_timekeeping', function (Blueprint $table) {
            $table->id();
            $table->string("employee_id");
            $table->string("biometric_number");
            $table->string("biotime_log_id")->nullable();
            $table->string("schedule_time_id");
            $table->string("date");
            $table->string("worked_hours");
            $table->string("final_worked_hours")->default(0);
            $table->string("legal_holiday")->nullable();
            $table->string("special_holiday")->nullable();
            $table->string("regular_overtime")->nullable();
            $table->string("night_diff_hours")->nullable();
            $table->string("night_diff_overtime")->nullable();
            $table->string("legal_holiday_hours")->nullable();
            $table->string("legal_holiday_overtime")->nullable();
            $table->string("special_holiday_hours")->nullable();
            $table->string("special_holiday_overtime")->nullable();
            $table->string("tardiness")->nullable();
            $table->string("undertime")->nullable();
            $table->boolean("is_cancelled_off")->default(false);
            $table->string("travel_time")->default(0);
            $table->string("approved_overtime")->nullable()->default(0);
            $table->longText("remarks")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adjustments_timekeeping');
    }
};
