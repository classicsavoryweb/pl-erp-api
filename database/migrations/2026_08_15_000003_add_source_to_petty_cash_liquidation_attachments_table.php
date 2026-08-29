<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_liquidation_attachments', function (Blueprint $table) {
            // 'Manual' attachments keep file_path as a local path on the
            // hr_system_folder disk; 'CSHUB' attachments store the full CS Hub
            // file URL instead, fetched remotely on view.
            $table->string('source')->default('Manual')->after('file_name');
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_liquidation_attachments', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
