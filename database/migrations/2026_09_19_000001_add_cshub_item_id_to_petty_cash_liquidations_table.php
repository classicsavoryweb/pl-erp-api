<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // CS Hub liquidations now break down into multiple `liquidation_items` (own
    // expense type + supplier each) sharing one trans_no, so trans_no can no
    // longer be unique per ERP liquidation row — uniqueness moves to the
    // per-item cshub_item_id instead.
    public function up(): void
    {
        Schema::table('petty_cash_liquidations', function (Blueprint $table) {
            $table->dropUnique(['cshub_trans_no']);
            $table->unsignedBigInteger('cshub_item_id')->nullable()->unique()->after('cshub_id');
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_liquidations', function (Blueprint $table) {
            $table->dropColumn('cshub_item_id');
            $table->unique('cshub_trans_no');
        });
    }
};
