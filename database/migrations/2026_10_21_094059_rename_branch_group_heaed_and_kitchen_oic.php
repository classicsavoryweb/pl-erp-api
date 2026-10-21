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
        Schema::table('branches', function (Blueprint $table) {
            $table->renameColumn('group_head', 'area_head');
            $table->renameColumn('group_head_employee_number', 'area_head_employee_number');
            $table->renameColumn('kitchen_oic', 'lab_head');
            $table->renameColumn('kitchen_oic_employee_number', 'lab_head_employee_number');
            $table->renameColumn('kitchen_oic_2', 'lab_head_2');
            $table->renameColumn('kitchen_oic_2_employee_number', 'lab_head_2_employee_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->renameColumn('area_head', 'group_head');
            $table->renameColumn('area_head_employee_number', 'group_head_employee_number');
            $table->renameColumn('lab_head', 'kitchen_oic');
            $table->renameColumn('lab_head_employee_number', 'kitchen_oic_employee_number');
            $table->renameColumn('lab_head_2', 'kitchen_oic_2');
            $table->renameColumn('lab_head_2_employee_number', 'kitchen_oic_2_employee_number');
        });
    }
};
