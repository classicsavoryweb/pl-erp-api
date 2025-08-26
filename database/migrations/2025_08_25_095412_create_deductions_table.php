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
        Schema::create('deductions', function (Blueprint $table) {
            $table->id();
            $table->string("description");
            $table->string("debit_id");
            $table->string("credit_id");
            $table->string("amortization_vendor_id");
            $table->string("amortization_debit_id");
            $table->string("amortization_credit_id");
            $table->string("end_loan_vendor_id");
            $table->string("end_loan_debit_id");
            $table->string("end_loan_credit_id");
            $table->string("status");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deductions');
    }
};
