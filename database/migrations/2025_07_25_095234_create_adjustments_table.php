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
        Schema::create('adjustments', function (Blueprint $table) {
            $table->id();
            $table->string("employee_id");
            $table->string("biometric_number");
            $table->string("date");
            $table->string("days")->nullable();
            $table->string("cancelled_rd")->nullable();
            $table->string("taxable_amount")->nullable();
            $table->string("amount_paid")->nullable();
            $table->string("correct_amount_paid")->nullable();
            $table->string("actual_schedule")->nullable();
            $table->string("type")->nullable();
            $table->string("status")->nullable();
            $table->longText("remarks")->nullable();
            $table->string("added_by")->nullable();
            $table->string("added_at")->nullable();
            $table->string("approved_by")->nullable();
            $table->string("approved_at")->nullable();
            $table->string("unapproved_by")->nullable();
            $table->string("unapproved_at")->nullable();
            $table->string("timekeeping_summary_id")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adjustments');
    }
};
