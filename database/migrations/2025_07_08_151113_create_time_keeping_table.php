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
        Schema::create('time_keeping', function (Blueprint $table) {
            $table->id();
            $table->string("employee_id");
            $table->string("biometric_number");
            $table->string("biotime_log_id");
            $table->string("schedule_time_id");
            $table->string("date");
            $table->string("worked_hours");
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
            $table->string("remarks")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_keeping');
    }
};
