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
        Schema::table('bir_2316', function (Blueprint $table) {
            $table->decimal('smw_rate_per_day', 13, 2)->default(0)->after('is_mwe'); // item 9
            $table->decimal('smw_rate_per_month', 13, 2)->default(0)->after('smw_rate_per_day'); // item 10
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bir_2316', function (Blueprint $table) {
            $table->dropColumn(['smw_rate_per_day', 'smw_rate_per_month']);
        });
    }
};
