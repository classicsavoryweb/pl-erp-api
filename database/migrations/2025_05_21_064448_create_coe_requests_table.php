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
        Schema::create('coe_requests', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id');
            $table->string('date_requested');
            $table->string("include_salary")->default("No");
            $table->string("coe_format");
            $table->string("places")->nullable();
            $table->string("coe_reason")->nullable();
            $table->string("status")->default("Pending");
            $table->string("leave_from")->nullable();
            $table->string("leave_to")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coe_requests');
    }
};
