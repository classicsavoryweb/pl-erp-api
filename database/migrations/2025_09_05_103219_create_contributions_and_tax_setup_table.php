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
        Schema::create('contributions_and_tax', function (Blueprint $table) {
            $table->id();
            $table->string("vendor_id");
            $table->string("debit_id");
            $table->string("credit_id");
            $table->string("vendor_name");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contributions_and_tax');
    }
};
