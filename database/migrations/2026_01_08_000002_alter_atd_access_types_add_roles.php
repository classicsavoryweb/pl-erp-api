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
        Schema::table('atd_access_types', function (Blueprint $table) {
            if (!Schema::hasColumn('atd_access_types', 'roles')) {
                $table->json('roles')->nullable()->after('category');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('atd_access_types', function (Blueprint $table) {
            if (Schema::hasColumn('atd_access_types', 'roles')) {
                $table->dropColumn('roles');
            }
        });
    }
};
