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
        Schema::table('converter_main', function (Blueprint $table) {
            $table->string("bank_account_type_id")->nullable();
            $table->string("transaction_type_id")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('converter_main', function (Blueprint $table) {
            //
        });
    }
};
