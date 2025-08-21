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
        Schema::create('sss', function (Blueprint $table) {
            $table->id();
            $table->string("salary_range_min");
            $table->string("salary_range_max");
            $table->string("salary_credit");
            $table->string("employer_ss");
            $table->string("employer_cc");
            $table->string("employee_ss");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sss');
    }
};
