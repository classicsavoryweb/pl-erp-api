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
        Schema::table('atd_access_types', function (Blueprint $table) {
            if (!Schema::hasColumn('atd_access_types', 'deduction_id')) {
                $table->unsignedBigInteger('deduction_id')->nullable()->after('roles');
                $table->foreign('deduction_id')->references('id')->on('deductions')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('atd_access_types', function (Blueprint $table) {
            if (Schema::hasColumn('atd_access_types', 'deduction_id')) {
                $table->dropForeign(['deduction_id']);
                $table->dropColumn('deduction_id');
            }
        });
    }
};
