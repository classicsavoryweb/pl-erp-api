<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_issuances', function (Blueprint $table) {
            $table->unsignedBigInteger('cshub_id')->nullable()->after('cshub_trans_no');
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_issuances', function (Blueprint $table) {
            $table->dropColumn('cshub_id');
        });
    }
};
