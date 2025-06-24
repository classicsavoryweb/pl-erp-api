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
        Schema::table('tardiness', function (Blueprint $table) {
            $table->timestamp("nte_date_sent")->after("nte_status")->nullable();
            $table->timestamp("da_date_sent")->after("da_status")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tardiness', function (Blueprint $table) {
            //
        });
    }
};
