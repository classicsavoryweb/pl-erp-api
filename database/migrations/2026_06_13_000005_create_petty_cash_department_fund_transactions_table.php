<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_department_fund_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('custodian_id');
            $table->unsignedBigInteger('transaction_type_id');
            $table->string('report_id')->nullable();
            $table->decimal('cash_fund', 13, 2)->nullable();
            $table->text('reason');
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamps();

            $table->foreign('custodian_id', 'pcdft_custodian_fk')
                ->references('id')->on('petty_cash_custodians')->restrictOnDelete();
            $table->foreign('transaction_type_id', 'pcdft_type_fk')
                ->references('id')->on('petty_cash_transaction_types')->restrictOnDelete();
            $table->foreign('created_by_id', 'pcdft_created_by_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_department_fund_transactions');
    }
};
