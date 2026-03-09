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
        Schema::table('incentives', function (Blueprint $table) {
            $table->json("additional_days")->nullable();
            $table->integer("month")->default(1);
            $table->integer("year")->default(2000);
            $table->decimal('first_cut_off_worked_days')->nullable()->change();
            $table->decimal('second_cut_off_worked_days')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('incentives', function (Blueprint $table) {

        });
    }
};
