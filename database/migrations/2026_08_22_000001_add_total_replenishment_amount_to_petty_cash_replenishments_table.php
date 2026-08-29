<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_replenishments', function (Blueprint $table) {
            $table->decimal('total_replenishment_amount', 15, 2)->default(0)->after('total_overunder_amount');
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_replenishments', function (Blueprint $table) {
            $table->dropColumn('total_replenishment_amount');
        });
    }
};
