<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_replenishments', function (Blueprint $table) {
            $table->decimal('total_unliquidated_amount', 15, 2)->default(0)->after('total_overunder_amount');
        });

        // backfill from snapshot table
        DB::statement("
            UPDATE petty_cash_replenishments r
            JOIN (
                SELECT petty_cash_replenishment_id, SUM(amount) AS total_unliquidated
                FROM petty_cash_replenishment_snapshots
                WHERE status_at_snapshot = 'Unliquidated'
                GROUP BY petty_cash_replenishment_id
            ) s ON s.petty_cash_replenishment_id = r.id
            SET r.total_unliquidated_amount = s.total_unliquidated
        ");
    }

    public function down(): void
    {
        Schema::table('petty_cash_replenishments', function (Blueprint $table) {
            $table->dropColumn('total_unliquidated_amount');
        });
    }
};
