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
        Schema::create('payroll_details', function (Blueprint $table) {
            $table->id();
            $table->string("employee_id");
            $table->string("payroll_id")->nullable();
            $table->string("pay_date");
            $table->string("home_branch_id");
            $table->string("position_id");
            $table->decimal("daily_rate", 12, 2)->default(0);
            $table->decimal("hourly_rate", 12, 5)->default(0);
            $table->decimal("minute_rate", 12, 5)->default(0);
            
            //timekeeping summary details
            $table->string("regular_days")->nullable();
            $table->string("special_leave")->nullable();
            $table->string("cancelled_off")->nullable();
            $table->string("special_holiday")->nullable();
            $table->string("legal_holiday")->nullable();
            $table->string("regular_overtime")->nullable();
            $table->string("night_differential")->nullable();
            $table->string("night_differential_overtime")->nullable();
            $table->string("legal_holiday_overtime")->nullable();
            $table->string("special_holiday_overtime")->nullable();
            $table->string("undertime")->nullable();
            $table->string("tardiness")->nullable();
            $table->string("travel_time")->nullable();
            $table->string("adjustment_amount")->nullable();

            $table->json("undertime_minutes")->nullable();
            $table->json("tardiness_minutes")->nullable();
            $table->longText("remarks")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_details');
    }
};
