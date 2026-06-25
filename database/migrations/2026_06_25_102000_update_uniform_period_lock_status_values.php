<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('uniform_period_locks')->where('status', 'Open')->update(['status' => 'active']);
        DB::table('uniform_period_locks')->where('status', 'Closed')->update(['status' => 'inactive']);

        DB::statement("ALTER TABLE uniform_period_locks MODIFY status VARCHAR(255) NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        DB::table('uniform_period_locks')->where('status', 'active')->update(['status' => 'Open']);
        DB::table('uniform_period_locks')->where('status', 'inactive')->update(['status' => 'Closed']);

        DB::statement("ALTER TABLE uniform_period_locks MODIFY status VARCHAR(255) NOT NULL DEFAULT 'Open'");
    }
};
