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
        Schema::create('converter_main', function (Blueprint $table) {
            $table->id();
            $table->string("batch_number")->nullable();
            $table->decimal("total_amount");
            $table->string("count");
            $table->string("status");
            $table->longText("remarks")->nullable();
            $table->string("added_by")->nullable();
            $table->string("posted_by")->nullable();
            $table->string("generated_by")->nullable();
            $table->string("voided_by")->nullable();
            $table->string("posted_at")->nullable();
            $table->string("generated_at")->nullable();
            $table->string("voided_at")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('converter_main');
    }
};
