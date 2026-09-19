<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_custodians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->decimal('total_cash_fund', 13, 2)->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('petty_cash_custodian_branch', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custodian_id')->constrained('petty_cash_custodians')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_custodian_branch');
        Schema::dropIfExists('petty_cash_custodians');
    }
};
