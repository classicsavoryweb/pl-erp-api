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
        Schema::create('scheduled_timekeeping_generation', function (Blueprint $table) {
            $table->id();
            $table->string("generated_by");
            $table->string("schedule");
            $table->string("status");
            $table->string("cut_off_id");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_timekeeping_generation');
    }
};
