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
        Schema::create('philhealth', function (Blueprint $table) {
            $table->id();
            $table->string("salary_range_min");
            $table->string("salary_range_max");
            $table->string("salary_base");
            $table->string("employee_share");
            $table->string("employer_share");
            $table->string("total_monthly_contribution");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('philhealth');
    }
};
