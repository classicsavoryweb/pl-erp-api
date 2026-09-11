<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uniform_physical_count_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('physical_count_id')->constrained('uniform_physical_counts')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees');
            $table->string('employee_name');
            $table->timestamps();

            $table->unique(['physical_count_id', 'employee_id'], 'uniform_pc_counter_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uniform_physical_count_counters');
    }
};
