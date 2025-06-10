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
        Schema::create('acting_capacity_extension', function (Blueprint $table) {
            $table->id();
            $table->string("acting_capacity_id");
            $table->string("end_date")->nullable();
            $table->string("reason")->nullable();
            $table->longText("remarks")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acting_capacity_extension');
    }
};
