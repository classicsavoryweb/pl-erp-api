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
        Schema::create('employees_monthly_allocation', function (Blueprint $table) {
            $table->id();
            $table->string("thirteenth_month_file_id");
            $table->string("employee_id");
            $table->string("branch_short_name")->nullable();
            $table->decimal("basic_pay_1st_cutoff", 13, 2);
            $table->decimal("basic_pay_2nd_cutoff", 13, 2);
            $table->decimal("total", 13, 2);
            $table->decimal("net_claim_13th_month", 13, 2);
            $table->decimal("net_claim_pay_leave", 13, 2);
            $table->decimal("net_claim_additional", 13, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees_monthly_allocation');
    }
};
