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
        Schema::create('incentives_position', function (Blueprint $table) {
            $table->id();
            $table->string("position_id");
            $table->decimal("amount", 12, 2)->default(0);
            $table->decimal("oic_additional_amount", 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incentives_position');
    }
};
