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
        Schema::create('bir_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_code')->unique();
            $table->string('tin')->nullable();
            $table->string('rdo_code')->nullable();
            $table->string('name')->nullable();
            $table->string('address')->nullable();
            $table->string('zip')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('atc')->default('WW010');
            $table->boolean('is_government')->default(false);
            $table->boolean('is_amended_return')->default(false);
            $table->boolean('has_tax_relief')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bir_settings');
    }
};
