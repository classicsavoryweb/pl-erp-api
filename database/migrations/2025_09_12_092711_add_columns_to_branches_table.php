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
        Schema::table('branches', function (Blueprint $table) {
            $table->string("ho_13th_debit")->nullable();
            $table->string("ho_13th_debit_1")->nullable();
            $table->string("ho_13th_credit")->nullable();
            $table->string("branch_ho_13th_debit")->nullable();
            $table->string("branch_ho_13th_debit_1")->nullable();
            $table->string("branch_ho_13th_credit")->nullable();
            $table->string("branch_ho_stat_debit")->nullable();
            $table->string("branch_ho_stat_credit")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            //
        });
    }
};
