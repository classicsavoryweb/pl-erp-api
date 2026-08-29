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

        Schema::create('request_and_issuances', function (Blueprint $table) {
            $table->id();
            $table->string('request_number');
            $table->string('employee_id');
            $table->string('employee_name');
            $table->string('issuance_type');
            $table->string('branch');
            $table->string('position');
            $table->string('date_hired');
            $table->string('date_requested');
            $table->string('date_reviewed')->nullable();
            $table->string('date_posted')->nullable();
            $table->string('date_cancelled')->nullable();
            $table->decimal('total_amount',12,2)->default(0);
            $table->string('status')->default('reviewed');
            $table->string('sent_email');
            $table->string('payroll_entry')->nullable();
            $table->string('payroll_sent_date')->nullable();
            $table->string('created_by')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->string('posted_by')->nullable();
            $table->string('cancelled_by')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_and_issuances');
    }
};
