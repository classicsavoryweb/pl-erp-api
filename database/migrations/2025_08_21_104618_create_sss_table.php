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
            $table->decimal("salary_range_min");
            $table->decimal("salary_range_max");
            $table->decimal("salary_credit");
            $table->decimal("employer_ss");
            $table->decimal("employer_cc");
            $table->decimal("employee_ss");
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
