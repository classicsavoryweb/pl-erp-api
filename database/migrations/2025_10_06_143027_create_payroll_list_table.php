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
        Schema::create('payroll_list', function (Blueprint $table) {
            $table->id();
            $table->string("employee_id");
            $table->boolean("is_included")->default(false);
            $table->boolean("is_statutory_only")->default(false);
            $table->longText("remarks")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_list');
    }
};
