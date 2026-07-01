<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uniform_period_locks', function (Blueprint $table) {
            if (Schema::hasColumn('uniform_period_locks', 'remarks')) {
                $table->dropColumn('remarks');
            }
        });
    }

    public function down(): void
    {
        Schema::table('uniform_period_locks', function (Blueprint $table) {
            if (!Schema::hasColumn('uniform_period_locks', 'remarks')) {
                $table->text('remarks')->nullable()->after('status');
            }
        });
    }
};
