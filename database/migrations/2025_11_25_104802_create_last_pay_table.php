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
        Schema::create('last_pay', function (Blueprint $table) {
            $table->id();
            $table->string("last_pay_number")->nullable(true);
            $table->string("employee_id");
            $table->decimal("payroll_hold")->default(0);
            $table->decimal("thirteenth_month")->default(0);
            $table->string("year");
            $table->decimal("sl_vl")->default(0);
            $table->decimal("tax_refund")->default(0);
            $table->decimal("incentives")->default(0);
            $table->decimal("total_deductions")->default(0);
            $table->decimal("sales")->default(0);
            $table->decimal("meal")->default(0);
            $table->decimal("last_pay")->default(0);
            $table->string("status")->default("Unposted");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('last_pay');
    }
};
