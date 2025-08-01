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
        Schema::table('adjustments_timekeeping', function (Blueprint $table) {
            $table->string("cancelled_off_hours")->after("is_cancelled_off")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('adjustments_timekeeping', function (Blueprint $table) {
            //
        });
    }
};
