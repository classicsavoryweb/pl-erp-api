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
        Schema::table('employee_offenses', function (Blueprint $table) {
            $table->string('sanction_id_two')->nullable();
            $table->string('sanction_id_three')->nullable();
            $table->string('date_memo_three')->nullable();
            $table->string('remarks_one')->nullable();
            $table->string('remarks_two')->nullable();
            $table->String('remarks_three')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_offenses', function (Blueprint $table) {
            //
        });
    }
};
