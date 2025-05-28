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
        Schema::create('tardiness', function (Blueprint $table) {
            $table->id();
            $table->string("employee_id");
            $table->string("branch_id");
            $table->string("month");
            $table->string("year");
            $table->string("total_minutes");
            $table->string("tardiness_sanction_id")->nullable();
            $table->string("offense_punishment")->nullable();
            $table->longText("reason")->nullable();
            $table->string("status")->default("Pending");
            $table->string("da_status")->nullable();
            $table->string("nte_status")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tardiness');
    }
};
