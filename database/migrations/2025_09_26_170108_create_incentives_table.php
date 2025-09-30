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
        Schema::create('incentives', function (Blueprint $table) {
            $table->id();
            $table->string("employee_id")->nullable();
            $table->decimal("incentive_amount")->nullable();
            $table->decimal("max_amount")->nullable();
            $table->string("remarks")->nullable();
            $table->string("pay_date")->nullable();
            $table->decimal("amount_rate")->nullable();
            $table->decimal("additional_incentive")->nullable();
            $table->integer("adjustment_days")->nullable();
            $table->decimal("adjustment_percent")->nullable();
            $table->string("status")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incentive_history');
    }
};
