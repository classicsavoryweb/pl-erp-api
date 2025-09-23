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
        Schema::create('philhealth_percentage_base', function (Blueprint $table) {
            $table->id();
            $table->string("philhealth_formula");
            $table->decimal("percentage");
            $table->decimal("management_max");
            $table->decimal("philhealth_cap");
            $table->decimal("philhealth_max");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('philhealth_percentage_base');
    }
};
