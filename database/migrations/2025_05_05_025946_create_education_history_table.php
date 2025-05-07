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
        Schema::create('education_history', function (Blueprint $table) {
            $table->id();
            $table->string("employee_id");
            $table->string("employee_number");
            $table->string("college")->nullable();
            $table->string("college_address")->nullable();
            $table->string("course")->nullable();
            $table->string("college_date_started")->nullable();
            $table->string("college_date_graduated")->nullable();
            $table->string("highschool")->nullable();
            $table->string("highschool_address")->nullable();
            $table->string("highschool_date_started")->nullable();
            $table->string("highschool_date_graduated")->nullable();
            $table->string("remarks")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('education_history');
    }
};
