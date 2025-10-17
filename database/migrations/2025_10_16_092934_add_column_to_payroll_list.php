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
            $table->boolean("is_mancom")->default(false)->after("is_statutory_only");
            $table->boolean("is_on_maternity_leave")->default(false)->after("is_mancom");
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
