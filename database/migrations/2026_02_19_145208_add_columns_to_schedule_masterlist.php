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
        Schema::table('schedule_masterlist', function (Blueprint $table) {
            $table->boolean("is_flexible")->default(0);
            $table->boolean("compute_break_time")->default(0);
            $table->boolean("compute_travel_time")->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedule_masterlist', function (Blueprint $table) {
            //
        });
    }
};
