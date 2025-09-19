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
        Schema::create('payroll', function (Blueprint $table) {
            $table->id();
            $table->string("employee_id");
            $table->string("timekeeping_summary_id");
            $table->string("timekeeping_branch_id");
            $table->string("pay_date");
            $table->string("cut_off");
            $table->string("start_date");
            $table->string("end_date");
            $table->string("daily_rate");
            $table->string("hourly_rate");
            $table->string("pay_type");
            $table->string("tax_status");

            //amounts
            $table->string("salary");
            $table->string("tardiness_amount")->default(0);
            $table->string("undertime_amount")->default(0);
            $table->string("overtime_amount")->default(0);
            $table->string("legal_holiday_amount")->default(0);
            $table->string("legal_holiday_overtime_amount")->default(0);
            $table->string("special_holiday_amount")->default(0);
            $table->string("special_holiday_overtime_amount")->default(0);
            $table->string("night_differential_amount")->default(0);
            $table->string("night_differential_overtime_amount")->default(0);
            $table->string("taxable_adjustment")->default(0);
            $table->string("non_taxable_adjustment")->default(0);
            $table->string("cancelled_off")->default(0);
            $table->string("gross_pay")->default(0);

            //statutories
            $table->string("employee_sss")->default(0);
            $table->string("employer_sss")->default(0);
            $table->string("employee_pagibig")->default(0);
            $table->string("employer_pagibig")->default(0);
            $table->string("employee_philhealth")->default(0);
            $table->string("employer_philhealth")->default(0);
            $table->string("withholding_tax")->default(0);

            //deductions
            $table->json("deductions")->nullable(); //[ { deduction_id, deduction_type, amount} ]
            $table->string("total_deductions")->default(0);
            $table->string("manual_deductions")->default(0);

            $table->string("net_pay")->default(0);
            $table->string("status");

            $table->longText("remarks")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll');
    }
};
