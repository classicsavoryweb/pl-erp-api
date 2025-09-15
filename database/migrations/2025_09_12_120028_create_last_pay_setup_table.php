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
        Schema::create('last_pay_setup', function (Blueprint $table) {
            $table->id();
            $table->string("debit_id")->nullable();
            $table->string("credit_id")->nullable();
            $table->string("setup_type")->nullable();
            $table->string("vendor_id")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('last_pay_setup');
    }
};
