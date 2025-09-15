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
        Schema::create('cut_off', function (Blueprint $table) {
            $table->id();
            $table->string("cut_off");
            $table->boolean("is_timekeeping_locked")->default(false)->nullable();
            $table->boolean("is_cutoff_initialized")->default(false)->nullable();
            $table->boolean("is_cutoff_locked")->default(false)->nullable();
            $table->string("pay_date")->nullable();
            $table->string("lock_unlocked_date")->nullable();
            $table->string("initialized_date")->nullable();
            $table->string("lock_unlocked_by")->nullable();
            $table->string("initialized_by")->nullable();
            $table->string("cutoff_locked_by")->nullable();
            $table->string("cutoff_locked_date")->nullable();
            $table->string("status")->default("inactive");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cut_off');
    }
};
