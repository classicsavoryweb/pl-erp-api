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
        Schema::table('tax', function (Blueprint $table) {
            $table->decimal("range_min", 12, 2)->change();
            $table->decimal("range_max", 12, 2)->change();
            $table->decimal("salary_base", 12, 2)->change();
            $table->decimal("percentage", 12, 2)->change();
            $table->decimal("exemption", 12, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
