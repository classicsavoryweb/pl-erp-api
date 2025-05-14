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
        Schema::create('employee_offenses', function (Blueprint $table) {
            $table->id();
            $table->string('offense_id');
            $table->string('employee_id');
            $table->string('sanction_id')->nullable();
            $table->string('transgression')->nullable();
            $table->string('repetition')->nullable();
            $table->longText('summary')->nullable();
            $table->string('date_committed')->nullable();
            $table->string('offense_action')->nullable();
            $table->string('date_served_ao')->nullable();
            $table->longText('suspension_dates')->nullable();
            $table->string('date_served_da')->nullable();
            $table->longText('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_offenses');
    }
};
