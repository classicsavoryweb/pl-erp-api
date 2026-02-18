<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biotime_logs', function (Blueprint $table) {
            $table->string('status', 20)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('biotime_logs', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
