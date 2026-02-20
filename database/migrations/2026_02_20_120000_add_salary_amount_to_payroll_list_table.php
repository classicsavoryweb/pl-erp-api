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
        Schema::table('payroll_list', function (Blueprint $table) {
            $table->decimal('salary_amount', 13, 2)->default(0)->after('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_list', function (Blueprint $table) {
            $table->dropColumn('salary_amount');
        });
    }
    
};
