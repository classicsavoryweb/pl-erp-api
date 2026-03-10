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
            $table->dropColumn('bank_account_type_id');
            $table->dropColumn('bank_account_number');
            $table->string("bank_details_id")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_list', function (Blueprint $table) {
            //
        });
    }
};
