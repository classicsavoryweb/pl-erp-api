<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_liquidation_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('petty_cash_liquidation_id');
            $table->unsignedBigInteger('petty_cash_replenishment_id');
            $table->string('action');
            $table->text('reason');
            $table->json('old_values')->nullable();
            $table->json('new_values');
            $table->unsignedBigInteger('performed_by_id')->nullable();
            $table->timestamps();

            $table->foreign('petty_cash_liquidation_id', 'pclal_liquidation_fk')
                ->references('id')->on('petty_cash_liquidations')->onDelete('cascade');
            $table->foreign('petty_cash_replenishment_id', 'pclal_replenishment_fk')
                ->references('id')->on('petty_cash_replenishments')->onDelete('cascade');
            $table->foreign('performed_by_id', 'pclal_performed_by_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_liquidation_audit_logs');
    }
};
