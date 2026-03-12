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
        Schema::table('time_keeping', function (Blueprint $table) {
            $table->integer('manual_tardiness_minutes')->nullable()->after('manual_undertime_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('time_keeping', function (Blueprint $table) {
            $table->dropColumn('manual_tardiness_minutes');
        });
    }
};
