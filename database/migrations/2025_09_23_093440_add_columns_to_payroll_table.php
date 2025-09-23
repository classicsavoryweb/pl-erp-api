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
        Schema::table('payroll', function (Blueprint $table) {
            $table->string("taxable_income")->default(0);
            $table->string("contribution_total")->default(0);
            $table->string("additional_working_hours_total")->default(0);
            $table->string("working_hours_deduction_total")->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll', function (Blueprint $table) {
            //
        });
    }
};
