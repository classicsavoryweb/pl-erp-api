<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('uniform_physical_count_items')->update([
            'total_amount' => DB::raw('physical_count * standard_cost'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('uniform_physical_count_items')->update([
            'total_amount' => 0,
        ]);
    }
};
