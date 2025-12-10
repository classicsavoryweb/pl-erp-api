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
        Schema::create('thirteenth_month_pay', function (Blueprint $table) {
            $table->id();
            $table->string("thirteenth_month_file_id");
            $table->string("employee_id");
            $table->decimal("december_2nd_cutoff", 13, 2);
            $table->decimal("january_1st_cutoff", 13, 2);
            $table->decimal("january_2nd_cutoff", 13, 2);
            $table->decimal("february_1st_cutoff", 13, 2);
            $table->decimal("february_2nd_cutoff", 13, 2);
            $table->decimal("march_1st_cutoff", 13, 2);
            $table->decimal("march_2nd_cutoff", 13, 2);
            $table->decimal("april_1st_cutoff", 13, 2);
            $table->decimal("april_2nd_cutoff", 13, 2);
            $table->decimal("may_1st_cutoff", 13, 2);
            $table->decimal("may_2nd_cutoff", 13, 2);
            $table->decimal("june_1st_cutoff", 13, 2);
            $table->decimal("june_2nd_cutoff", 13, 2);
            $table->decimal("july_1st_cutoff", 13, 2);
            $table->decimal("july_2nd_cutoff", 13, 2);
            $table->decimal("august_1st_cutoff", 13, 2);
            $table->decimal("august_2nd_cutoff", 13, 2);
            $table->decimal("september_1st_cutoff", 13, 2);
            $table->decimal("september_2nd_cutoff", 13, 2);
            $table->decimal("october_1st_cutoff", 13, 2);
            $table->decimal("october_2nd_cutoff", 13, 2);
            $table->decimal("november_1st_cutoff", 13, 2);
            $table->decimal("november_2nd_cutoff", 13, 2);
            $table->decimal("december_1st_cutoff", 13, 2);
            $table->string("year");
            $table->decimal("total", 13, 2);
            $table->decimal("net_claim", 13, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thirteenth_month_pay');
    }
};
