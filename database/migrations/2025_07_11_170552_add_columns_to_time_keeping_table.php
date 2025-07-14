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
        Schema::table('time_keeping', function (Blueprint $table) {
            $table->boolean("is_cancelled_off")->after("undertime")->default(false);
            $table->string("travel_time")->after("undertime")->default(0);
            $table->string("overtime_status")->after("undertime")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('time_keeping', function (Blueprint $table) {
            //
        });
    }
};
