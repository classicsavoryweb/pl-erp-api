<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cs_hub_transaction_type', function (Blueprint $table) {
            $table->id();
            $table->string("description");
            $table->string("ho_debit")->nullable();
            $table->string("ho_credit")->nullable();
            $table->string("branch_debit")->nullable();
            $table->string("branch_credit")->nullable();
            $table->string("status")->default("active");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cs_hub_transaction_type');
    }
};
