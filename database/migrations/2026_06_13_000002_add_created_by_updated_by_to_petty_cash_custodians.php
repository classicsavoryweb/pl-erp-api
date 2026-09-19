<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_custodians', function (Blueprint $table) {
            $table->foreignId('created_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_custodians', function (Blueprint $table) {
            $table->dropForeign(['created_by_id']);
            $table->dropForeign(['updated_by_id']);
            $table->dropColumn(['created_by_id', 'updated_by_id']);
        });
    }
};
