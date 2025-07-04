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
        Schema::create('schedule_masterlist', function (Blueprint $table) {
            $table->id();
            $table->string("start_time");
            $table->string("end_time");
            $table->string("selection");
            $table->string("classification");
            $table->string("time_break");
            $table->string("total_hours");
            $table->string("days");
            $table->string("required")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_masterlist');
    }
};
