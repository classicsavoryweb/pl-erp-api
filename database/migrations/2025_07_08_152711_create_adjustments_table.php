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
            $table->string('cutoff_period');
            $table->string('employee_id');
            $table->string('branch');
            $table->string('reg_days');
            $table->string('cancelled_rd');
            $table->string('adjustable_tax');
            $table->string('remarks');
            $table->string('date');
            $table->string('status');
            $table->string('new_timein');
            $table->string('new_breakout');
            $table->string('new_breakin');
            $table->string('new_timeout');
            $table->string('amount_paid');
            $table->string('should_be_paid');
            $table->string('adjustment_type');
            $table->string('added_date');
            $table->string('added_by');
            $table->string('approved_date');
            $table->string('approved_by');
            $table->string('load_status');
            $table->string('load_date');
            $table->string('unapproved_date');
            $table->string('actual_schedule');
            $table->string('sched_timein');
            $table->string('sched_timeout');
            $table->string('cutoff_counter');
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
