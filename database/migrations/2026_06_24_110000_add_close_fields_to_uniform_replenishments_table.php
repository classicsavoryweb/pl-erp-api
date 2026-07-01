<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uniform_replenishments', function (Blueprint $table) {
            $table->string('closed_by')->nullable()->after('cancelled_reason');
            $table->timestamp('closed_at')->nullable()->after('closed_by');
            $table->text('closed_reason')->nullable()->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('uniform_replenishments', function (Blueprint $table) {
            $table->dropColumn(['closed_by', 'closed_at', 'closed_reason']);
        });
    }
};
