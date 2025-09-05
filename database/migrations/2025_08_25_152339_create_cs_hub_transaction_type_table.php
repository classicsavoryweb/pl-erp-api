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
        Schema::create('cs_hub_transaction_type', function (Blueprint $table) {
            $table->id();
            $table->string("description");
            $table->string("source_id")->nullable();
            $table->string("transaction_type")->nullable();
            $table->string("exam_type")->nullable();
            $table->string("deduction_id")->nullable();
            $table->string("deduction_status_positive")->nullable();
            $table->string("deduction_status_negative")->nullable();
            $table->string("credit_status_positive")->nullable();
            $table->string("credit_status_negative")->nullable();
            $table->string("email_status")->nullable();
            $table->string("ho_debit")->nullable();
            $table->string("ho_credit")->nullable();
            $table->string("branch_debit")->nullable();
            $table->string("branch_credit")->nullable();
            $table->string("advances_debit")->nullable();
            $table->string("status")->default("active");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cs_hub_transaction_type');
    }
};
