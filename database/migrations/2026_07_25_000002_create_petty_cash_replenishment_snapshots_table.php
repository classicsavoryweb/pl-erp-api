<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_replenishment_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('petty_cash_replenishment_id');
            $table->unsignedBigInteger('petty_cash_issuance_id')->nullable();
            $table->string('pcv_number');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('branch_name');
            $table->date('transaction_date');
            $table->string('requested_by_name');
            $table->decimal('amount', 15, 2);
            $table->decimal('unliquidated_amount', 15, 2)->default(0);
            $table->decimal('overunder_amount', 15, 2)->nullable();
            $table->string('status_at_snapshot');
            $table->timestamps();

            $table->index('petty_cash_replenishment_id', 'pcrs_replenishment_idx');
            $table->index('petty_cash_issuance_id', 'pcrs_issuance_idx');

            $table->foreign('petty_cash_replenishment_id', 'pcrs_replenishment_fk')
                ->references('id')->on('petty_cash_replenishments')->cascadeOnDelete();
            $table->foreign('petty_cash_issuance_id', 'pcrs_issuance_fk')
                ->references('id')->on('petty_cash_issuances')->nullOnDelete();
            $table->foreign('branch_id', 'pcrs_branch_fk')
                ->references('id')->on('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_replenishment_snapshots');
    }
};
