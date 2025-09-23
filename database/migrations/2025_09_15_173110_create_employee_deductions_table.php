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
        Schema::create('employee_deductions', function (Blueprint $table) {
            $table->id();
            $table->string("employee_id")->nullable();
            $table->string("deduction_id")->nullable();
            $table->string("reference_doc")->nullable();
            $table->string("months")->nullable();
            $table->decimal("amount")->nullable();
            $table->string("source_of_fund")->nullable();
            $table->string("start_date")->nullable();
            $table->string("end_date")->nullable();
            $table->decimal("monthly_amortization")->nullable();
            $table->decimal("balance")->nullable();
            $table->text("remarks")->nullable();
            $table->string("debit_id")->nullable();
            $table->string("credit_id")->nullable();
            $table->string("branch_id")->nullable();
            $table->string("block_payment")->nullable();
            $table->string("approved_by")->nullable();
            $table->string("approved_date")->nullable();
            $table->string("approved_status")->nullable();
            $table->string("deduction_ref")->nullable();
            $table->string("deduction_status")->nullable();
            $table->string("created_by")->nullable();
            $table->string("date_created")->nullable();
            $table->string("end_loan_reason")->nullable();
            $table->string("end_loan_status")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_deductions');
    }
};
