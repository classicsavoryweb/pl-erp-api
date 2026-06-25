<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('uniform_weighted_average_items');
        Schema::dropIfExists('uniform_weighted_averages');
    }

    public function down(): void
    {
        // Weighted Average is intentionally owned outside this scope.
    }
};
