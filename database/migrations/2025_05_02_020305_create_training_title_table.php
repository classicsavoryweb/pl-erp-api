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
        Schema::create('training_titles', function (Blueprint $table) {
            $table->id();
            $table->string('option');
            $table->string('title');
            $table->string('venue');
            $table->string('speaker');
            $table->string("status")->default("active");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_titles');
    }
};
