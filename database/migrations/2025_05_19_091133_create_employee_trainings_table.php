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
        Schema::create('employee_trainings', function (Blueprint $table) {
            $table->id();
            $table->string("employee_id");
            $table->string("training_title");
            $table->string("speaker");
            $table->string("venue");
            $table->string("batch")->nullable();
            $table->string("others")->default("No");
            $table->string("date")->nullable();
            $table->string("covered_date")->nullable();
            $table->string("score")->nullable();
            $table->string("option")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_trainings');
    }
};
