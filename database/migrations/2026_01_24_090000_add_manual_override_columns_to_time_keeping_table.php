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
            $table->boolean('is_manual_override')->default(false)->after('paid_holiday');
            $table->string('manual_tag')->nullable()->after('is_manual_override');
            $table->integer('manual_undertime_minutes')->nullable()->after('manual_tag');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('time_keeping', function (Blueprint $table) {
            $table->dropColumn([
                'is_manual_override',
                'manual_tag',
                'manual_undertime_minutes',
            ]);
        });
    }
};
