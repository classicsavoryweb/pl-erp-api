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
        Schema::table('overtime', function (Blueprint $table) {
            $table->string("hours_paid")->nullable();
            $table->string("timekeeping_id")->nullable();
            $table->string("adjustment_timekeeping_id")->nullable();
            $table->string("cutoff_paid")->nullable();
            $table->string("payment_type")->nullable(); // TAXABLE ADJUSTMENT | FORCE ADJUSTMENT | TIMEKEEPING
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('overtime', function (Blueprint $table) {
            //
        });
    }
};
