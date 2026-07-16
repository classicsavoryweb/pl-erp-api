<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_liquidation_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('petty_cash_liquidation_id');
            $table->foreign('petty_cash_liquidation_id', 'pc_liq_attach_liq_id_foreign')
                  ->references('id')->on('petty_cash_liquidations')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_liquidation_attachments');
    }
};
