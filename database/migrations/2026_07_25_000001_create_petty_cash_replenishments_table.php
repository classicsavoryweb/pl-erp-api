<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_replenishments', function (Blueprint $table) {
            $table->id();
            $table->string('replenishment_id')->unique();
            $table->date('date');
            $table->unsignedBigInteger('petty_cash_custodian_id');
            $table->string('branches_summary');
            $table->decimal('total_cash_issuance_amount', 15, 2)->default(0);
            $table->decimal('total_overunder_amount', 15, 2)->default(0);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamps();

            $table->foreign('petty_cash_custodian_id', 'pcr_custodian_fk')
                ->references('id')->on('petty_cash_custodians')->restrictOnDelete();
            $table->foreign('created_by_id', 'pcr_created_by_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_replenishments');
    }
};
