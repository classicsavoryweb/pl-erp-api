<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('atd_access_types')) {
            return;
        }

        DB::table('atd_access_types')->delete();

        Schema::table('atd_access_types', function (Blueprint $table) {
            if (Schema::hasColumn('atd_access_types', 'category')) {
                $table->dropColumn('category');
            }
            if (Schema::hasColumn('atd_access_types', 'roles')) {
                $table->dropColumn('roles');
            }
        });

        Schema::table('atd_access_types', function (Blueprint $table) {
            if (!Schema::hasColumn('atd_access_types', 'role_id')) {
                $table->unsignedBigInteger('role_id')->nullable()->after('id');
                $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
            }
            if (!Schema::hasColumn('atd_access_types', 'deduction_id')) {
                $table->unsignedBigInteger('deduction_id')->nullable()->after('role_id');
                $table->foreign('deduction_id')->references('id')->on('deductions')->nullOnDelete();
            }
            if (!Schema::hasColumn('atd_access_types', 'status')) {
                $table->string('status')->default('active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('atd_access_types')) {
            return;
        }

        Schema::table('atd_access_types', function (Blueprint $table) {
            if (!Schema::hasColumn('atd_access_types', 'category')) {
                $table->string('category')->nullable()->after('id');
            }
            if (!Schema::hasColumn('atd_access_types', 'roles')) {
                $table->json('roles')->nullable()->after('category');
            }
        });
    }
};
