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
        Schema::table('weighted_averages', function (Blueprint $table) {
            $table->string('price_updated_by')->nullable()->after('posted_at');
            $table->string('price_updated_at')->nullable()->after('price_updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weighted_averages', function (Blueprint $table) {
            $table->dropColumn(['price_updated_by', 'price_updated_at']);
        });
    }
};
