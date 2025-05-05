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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string("employee_number")->nullable();
            $table->string("first_name");
            $table->string("middle_name")->nullable();
            $table->string("last_name");
            $table->string("email")->nullable();
            $table->string("civil_status")->nullable();
            $table->string("gender")->nullable();
            $table->string("height")->nullable();
            $table->string("birthplace")->nullable();
            $table->string("address")->nullable();
            $table->string("provincial_address")->nullable();
            $table->string("region")->nullable();;
            $table->string("contact_number")->nullable();
            $table->string("tin")->nullable();
            $table->string("sss")->nullable();
            $table->string("philhealth")->nullable();
            $table->string("pagibig")->nullable();
            $table->string("referred_by")->nullable();
            $table->string("remarks")->nullable();
            $table->string("date_hired")->nullable();
            $table->string("type")->nullable();
            $table->string("agency_id")->nullable();
            $table->string("employee_status_id")->nullable();
            $table->string("position_id")->nullable();
            $table->string("station_id")->nullable();
            $table->string("branch_id")->nullable();
            $table->string("home_branch_id")->nullable();
            $table->string("status")->default("active");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
