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
        Schema::table('payroll_details', function (Blueprint $table) {
            $table->boolean("is_hold")->after("position_id")->default(false);
            $table->boolean("is_on_maternity_leave")->after("is_hold")->default(false);
            $table->boolean("is_mancom")->after("is_on_maternity_leave")->default(false);
            $table->boolean("is_complete_documents")->after("is_mancom")->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            //
        });
    }
};
