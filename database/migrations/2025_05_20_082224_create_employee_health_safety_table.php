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
        Schema::create('employee_health_safety', function (Blueprint $table) {
            $table->id();
            $table->string("employee_id");
            $table->string("diagnosis_id")->nullable();
            $table->string("consultation_date")->nullable();
            $table->string("last_duty_date")->nullable();
            $table->string("hso")->nullable();
            $table->string("chief_complaint")->nullable();
            $table->string("attending_physician")->nullable();
            $table->string("physician_name")->nullable();
            $table->string("swab_test")->nullable();
            $table->string("swab_test_date")->nullable();
            $table->string("swab_test_type")->nullable();
            $table->string("swab_test_result")->nullable();
            $table->string("return_to_work_date")->nullable();
            $table->string("quarantine_period_from")->nullable();
            $table->string("quarantine_period_to")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_health_safety');
    }
};
