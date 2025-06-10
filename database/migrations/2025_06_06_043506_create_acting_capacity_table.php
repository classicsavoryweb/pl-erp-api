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
        Schema::create('acting_capacity', function (Blueprint $table) {
            $table->id();
            $table->string("employee_id");
            $table->string("branch_id");
            $table->string("current_position_id");
            $table->string("recommended_position_id");
            $table->string("start_date");
            $table->string("end_date");
            $table->longText("remarks")->nullable();
            $table->string("period")->nullable();
            $table->string("bond_amount")->nullable();
            $table->string("effectivity_date")->nullable();
            $table->string("status")->default("Pending");
            $table->string("generated_by")->nullable();
            $table->timestamp("generated_date")->nullable();
            $table->timestamp("confirmed_date")->nullable();
            $table->string("confirmed_by")->nullable();
            $table->string("rejected_reason")->nullable();
            $table->timestamp("rejected_date")->nullable();
            $table->string("rejected_by")->nullable();
            $table->string("recommendation_letter")->nullable();
            $table->string("bond_year")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acting_capacity');
    }
};
