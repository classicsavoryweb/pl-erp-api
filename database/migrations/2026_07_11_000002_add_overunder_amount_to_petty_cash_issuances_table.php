<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_issuances', function (Blueprint $table) {
            $table->decimal('overunder_amount', 15, 2)->nullable()->after('unliquidated_amount');
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_issuances', function (Blueprint $table) {
            $table->dropColumn('overunder_amount');
        });
    }
};
