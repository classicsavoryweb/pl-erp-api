<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_transaction_types', function (Blueprint $table) {
            $table->boolean('allow_negative')->default(false)->after('effect_direction');
        });

        // walang doctrine/dbal, kaya raw SQL na lang para palawakin yung enum
        DB::statement("ALTER TABLE petty_cash_transaction_types MODIFY effect_direction ENUM('add', 'subtract', 'both') NOT NULL DEFAULT 'add'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE petty_cash_transaction_types MODIFY effect_direction ENUM('add', 'subtract') NOT NULL DEFAULT 'add'");

        Schema::table('petty_cash_transaction_types', function (Blueprint $table) {
            $table->dropColumn('allow_negative');
        });
    }
};
