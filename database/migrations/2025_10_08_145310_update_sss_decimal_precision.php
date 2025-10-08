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
       Schema::table('sss', function (Blueprint $table) {
            $table->decimal("salary_range_min", 13, 2)->change();
            $table->decimal("salary_range_max", 13, 2)->change();
            $table->decimal("salary_credit", 13, 2)->change();
            $table->decimal("employer_ss", 13, 2)->change();
            $table->decimal("employer_cc", 13, 2)->change();
            $table->decimal("employee_ss", 13, 2)->change();
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
