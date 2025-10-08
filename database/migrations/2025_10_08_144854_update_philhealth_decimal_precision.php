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
        Schema::table('philhealth', function (Blueprint $table) {
            $table->decimal('salary_range_min', 12, 2)->change();
            $table->decimal('salary_range_max', 12, 2)->change();
            $table->decimal('salary_base', 12, 2)->change();
            $table->decimal('employee_share', 12, 2)->change();
            $table->decimal('employer_share', 12, 2)->change();
            $table->decimal('total_monthly_contribution', 12, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
