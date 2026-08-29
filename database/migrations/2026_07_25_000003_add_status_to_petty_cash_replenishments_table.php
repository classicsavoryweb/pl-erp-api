<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_replenishments', function (Blueprint $table) {
            $table->string('status')->default('Pending')->after('total_overunder_amount');
            $table->string('void_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_replenishments', function (Blueprint $table) {
            $table->dropColumn(['status', 'void_reason']);
        });
    }
};
