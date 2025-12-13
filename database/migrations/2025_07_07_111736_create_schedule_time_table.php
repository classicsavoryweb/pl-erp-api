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
        Schema::create('schedule_time', function (Blueprint $table) {
            $table->id();
            $table->string("employee_id");
            $table->string("biometric_number");
            $table->string("date");
            $table->string("timetable");
            $table->string("check_in");
            $table->string("check_out");
            $table->string("branch_id");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_time');
    }
};
