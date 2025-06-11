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
        Schema::table('acting_capacity', function (Blueprint $table) {
            $table->string("oic_employee_number")->nullable();
            $table->string("branch_assignment_id")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('acting_capacity', function (Blueprint $table) {
            //
        });
    }
};
