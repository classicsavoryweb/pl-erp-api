<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_liquidations', function (Blueprint $table) {
            $table->string('cshub_trans_no')->nullable()->unique()->after('liquidation_id');
            $table->unsignedBigInteger('cshub_id')->nullable()->after('cshub_trans_no');
            $table->string('source')->default('Manual')->after('cshub_id');
        });

        // expense_type_id and supplier_vendor have no equivalent data on CS Hub's
        // side, so CS Hub-imported liquidations are stored with both left null
        // (doctrine/dbal isn't installed, so column type/nullability changes go
        // through raw SQL instead of Schema::table(...)->change()).
        Schema::table('petty_cash_liquidations', function (Blueprint $table) {
            $table->dropForeign(['expense_type_id']);
        });

        DB::statement('ALTER TABLE petty_cash_liquidations MODIFY expense_type_id BIGINT UNSIGNED NULL');

        Schema::table('petty_cash_liquidations', function (Blueprint $table) {
            $table->foreign('expense_type_id')->references('id')->on('expense_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_liquidations', function (Blueprint $table) {
            $table->dropForeign(['expense_type_id']);
        });

        DB::statement('ALTER TABLE petty_cash_liquidations MODIFY expense_type_id BIGINT UNSIGNED NOT NULL');

        Schema::table('petty_cash_liquidations', function (Blueprint $table) {
            $table->foreign('expense_type_id')->references('id')->on('expense_types');
        });

        Schema::table('petty_cash_liquidations', function (Blueprint $table) {
            $table->dropColumn(['cshub_trans_no', 'cshub_id', 'source']);
        });
    }
};
