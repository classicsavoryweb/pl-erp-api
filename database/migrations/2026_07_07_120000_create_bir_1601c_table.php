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
        Schema::create('bir_1601c', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('start_date');
            $table->date('end_date');

            $table->decimal('total_compensation', 13, 2)->default(0);
            $table->decimal('statutory_minimum_wage', 13, 2)->default(0);
            $table->decimal('holiday_pay_ot_ndf', 13, 2)->default(0);
            $table->decimal('thirteenth_month_pay', 13, 2)->default(0);
            $table->decimal('ee_contributions', 13, 2)->default(0);
            $table->decimal('de_minimis_benefits', 13, 2)->default(0);
            $table->decimal('other_non_taxable_compensation', 13, 2)->default(0);
            $table->decimal('total_taxable_compensation', 13, 2)->default(0);
            $table->decimal('taxable_compensation_not_subject_to_wtax', 13, 2)->default(0);
            $table->decimal('net_taxable_compensation', 13, 2)->default(0);
            $table->decimal('total_taxes_withheld', 13, 2)->default(0);

            $table->decimal('add_less_adjustment', 13, 2)->default(0);
            $table->decimal('tax_still_due', 13, 2)->default(0);
            $table->decimal('total_remittance', 13, 2)->default(0);
            $table->decimal('total_amount_due', 13, 2)->default(0);

            $table->string('previous_month_1_period')->nullable();
            $table->date('previous_month_1_date_paid')->nullable();
            $table->string('previous_month_1_bank')->nullable();
            $table->string('previous_month_1_ror_number')->nullable();
            $table->decimal('previous_month_1_tax_paid', 13, 2)->default(0);
            $table->decimal('previous_month_1_tax_due', 13, 2)->default(0);

            $table->string('previous_month_2_period')->nullable();
            $table->date('previous_month_2_date_paid')->nullable();
            $table->string('previous_month_2_bank')->nullable();
            $table->string('previous_month_2_ror_number')->nullable();
            $table->decimal('previous_month_2_tax_paid', 13, 2)->default(0);
            $table->decimal('previous_month_2_tax_due', 13, 2)->default(0);

            $table->string('status')->default('draft');
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->unsignedBigInteger('finalized_by')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bir_1601c');
    }
};
