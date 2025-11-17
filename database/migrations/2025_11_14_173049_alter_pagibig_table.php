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
        Schema::table('pagibig', function (Blueprint $table) {
            $table->dropColumn(["employee_share", "employer_share"]);
            $table->decimal("min_amount")->default(0);
            $table->decimal("max_amount")->default(0);
            $table->decimal("percentage")->default(0);
            $table->decimal("max_deduction")->default(0);
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
