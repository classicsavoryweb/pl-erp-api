<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cs_hub_transaction_type', function (Blueprint $table) {
            $table->string('batch_entry')->default('No')->after('credit_status_negative');
        });
    }

    public function down(): void
    {
        Schema::table('cs_hub_transaction_type', function (Blueprint $table) {
            $table->dropColumn('batch_entry');
        });
    }
};
