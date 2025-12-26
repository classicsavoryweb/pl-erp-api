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
        Schema::table('coe_requests', function (Blueprint $table) {
            $table->string("position")->nullable();
            $table->decimal("basic_salary", 11)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coe_requests', function (Blueprint $table) {
            //
        });
    }
};
