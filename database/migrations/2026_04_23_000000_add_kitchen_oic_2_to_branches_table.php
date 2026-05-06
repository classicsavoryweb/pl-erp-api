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
            if (!Schema::hasColumn('branches', 'kitchen_oic_2_employee_number')) {
                $table->string('kitchen_oic_2_employee_number')->nullable()->after('kitchen_oic');
            }

            if (!Schema::hasColumn('branches', 'kitchen_oic_2')) {
                $table->string('kitchen_oic_2')->nullable()->after('kitchen_oic_2_employee_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'kitchen_oic_2')) {
                $table->dropColumn('kitchen_oic_2');
            }

            if (Schema::hasColumn('branches', 'kitchen_oic_2_employee_number')) {
                $table->dropColumn('kitchen_oic_2_employee_number');
            }
        });
    }
};
