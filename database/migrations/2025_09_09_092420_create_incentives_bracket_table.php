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
        Schema::create('incentives_bracket', function (Blueprint $table) {
            $table->id();
            $table->integer("incentives_bracket_id")->nullable();
            $table->string("bracket_name")->nullable();
            $table->string("month")->nullable();
            $table->decimal("amount")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incentives_bracket');
    }
};
