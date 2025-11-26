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
        Schema::create('annual_tax', function (Blueprint $table) {
            $table->id();
            $table->decimal("minimum_amount", 9);
            $table->decimal("max_amount" , 9);
            $table->decimal("tax_lower_limit");
            $table->decimal("percentage");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('annual_tax');
    }
};
