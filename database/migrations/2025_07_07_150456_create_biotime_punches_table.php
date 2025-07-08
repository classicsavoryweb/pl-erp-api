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
        Schema::create('biotime_punches', function (Blueprint $table) {
            $table->id();
            $table->string("biotime_log_id");
            $table->string("date");
            $table->string("punch_time");
            $table->string("remarks")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biotime_punches');
    }
};
