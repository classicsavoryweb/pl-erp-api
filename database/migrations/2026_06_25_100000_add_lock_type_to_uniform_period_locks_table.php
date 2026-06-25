<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uniform_period_locks', function (Blueprint $table) {
            $table->string('lock_type')->default('Issuance')->after('period_name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('uniform_period_locks', function (Blueprint $table) {
            $table->dropColumn('lock_type');
        });
    }
};
