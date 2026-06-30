<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uniform_free_uniform_limits', function (Blueprint $table) {
            $table->id();
            $table->string('description')->unique();
            $table->unsignedInteger('allowed_count')->default(1);
            $table->string('status')->default('active');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uniform_free_uniform_limits');
    }
};
