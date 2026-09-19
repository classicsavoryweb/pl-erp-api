<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_transaction_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('cash_fund_required')->default(true);
            $table->boolean('report_id_required')->default(false);
            $table->enum('effect_direction', ['add', 'subtract'])->default('add');
            $table->enum('effect_target', ['remaining_cash_fund', 'total_cash_fund', 'both'])->default('remaining_cash_fund');
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_transaction_types');
    }
};
