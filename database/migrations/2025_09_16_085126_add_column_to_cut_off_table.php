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
        Schema::table('cut_off', function (Blueprint $table) {
            $table->string("start_date")->nullable()->after("cut_off");
            $table->string("end_date")->nullable()->after("start_date");
            $table->json("initializations")->nullable()->after("status");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cut_off', function (Blueprint $table) {
            //
        });
    }
};
