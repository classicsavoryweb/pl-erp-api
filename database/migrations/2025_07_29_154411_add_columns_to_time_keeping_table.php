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
            $table->string("cancelled_off_hours")->nullable()->after("is_cancelled_off");
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
