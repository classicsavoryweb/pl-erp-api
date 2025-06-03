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
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('branch_number')->unique();
            $table->string('name');
            $table->string('short_name');
            $table->string('address');
            $table->string('location');
            $table->string('date_open')->nullable();
            $table->string('company')->nullable();
            $table->string('branch_email')->nullable();
            $table->string('rate_code')->nullable();
            $table->string('branch_head_employee_number')->nullable();
            $table->string('group_head_employee_number')->nullable();
            $table->string('branch_head')->nullable();
            $table->string('group_head')->nullable();
            $table->string('kitchen_oic_employee_number')->nullable();
            $table->string('kitchen_oic')->nullable();
            $table->string('mancom_employee_number')->nullable();
            $table->string('mancom')->nullable();
            $table->string("status")->default("active");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
