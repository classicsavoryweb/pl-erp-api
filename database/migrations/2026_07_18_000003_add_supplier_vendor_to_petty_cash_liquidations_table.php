<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_liquidations', function (Blueprint $table) {
            $table->string('supplier_vendor')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_liquidations', function (Blueprint $table) {
            $table->dropColumn('supplier_vendor');
        });
    }
};
