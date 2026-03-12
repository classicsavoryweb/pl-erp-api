<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tardiness_disablers', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->boolean('is_disabled')->default(true);
            $table->timestamps();

            $table->unique(['month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tardiness_disablers');
    }
};
