<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_replenishments', function (Blueprint $table) {
            $table->unsignedBigInteger('reviewed_by_id')->nullable()->after('void_reason');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_id');
            $table->unsignedBigInteger('audited_by_id')->nullable()->after('reviewed_at');
            $table->timestamp('audited_at')->nullable()->after('audited_by_id');

            $table->foreign('reviewed_by_id', 'pcr_reviewed_by_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('audited_by_id', 'pcr_audited_by_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_replenishments', function (Blueprint $table) {
            $table->dropForeign('pcr_reviewed_by_fk');
            $table->dropForeign('pcr_audited_by_fk');
            $table->dropColumn(['reviewed_by_id', 'reviewed_at', 'audited_by_id', 'audited_at']);
        });
    }
};
