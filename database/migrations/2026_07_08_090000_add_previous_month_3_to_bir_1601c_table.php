<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bir_1601c', function (Blueprint $table) {
            $table->string('previous_month_3_period')->nullable();
            $table->date('previous_month_3_date_paid')->nullable();
            $table->string('previous_month_3_bank')->nullable();
            $table->string('previous_month_3_ror_number')->nullable();
            $table->decimal('previous_month_3_tax_paid', 13, 2)->default(0);
            $table->decimal('previous_month_3_tax_due', 13, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bir_1601c', function (Blueprint $table) {
            $table->dropColumn([
                'previous_month_3_period',
                'previous_month_3_date_paid',
                'previous_month_3_bank',
                'previous_month_3_ror_number',
                'previous_month_3_tax_paid',
                'previous_month_3_tax_due',
            ]);
        });
    }
};
