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
        Schema::table('cs_hub_transaction_type', function (Blueprint $table) {
            $table->boolean("is_block_payment")->default(false)->after("status");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cs_hub_transaction_type', function (Blueprint $table) {
            //
        });
    }
};
