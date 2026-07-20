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
        Schema::create('bir_2316', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('employee_id');
            $table->date('period_from');
            $table->date('period_to');
            $table->boolean('is_mwe')->default(false);
            $table->string('rdo_code')->nullable();

            $table->decimal('basic_salary_mwe', 13, 2)->default(0); 
            $table->decimal('thirteenth_month_pay', 13, 2)->default(0); 
            $table->decimal('sss_gsis_phic_hdmf_ee', 13, 2)->default(0); 
            $table->decimal('total_non_taxable', 13, 2)->default(0); 


            $table->decimal('basic_salary_taxable', 13, 2)->default(0); 
            $table->decimal('taxable_13th_month_excess', 13, 2)->default(0); 
            $table->decimal('overtime_pay_taxable', 13, 2)->default(0); 
            $table->decimal('total_taxable_regular', 13, 2)->default(0); 


            $table->decimal('gross_compensation', 13, 2)->default(0); 
            $table->decimal('less_non_taxable', 13, 2)->default(0); 
            $table->decimal('taxable_comp_present', 13, 2)->default(0); 
            $table->decimal('gross_taxable_comp', 13, 2)->default(0); 
            $table->decimal('tax_due', 13, 2)->default(0); 
            $table->decimal('taxes_withheld_present', 13, 2)->default(0); 
            $table->decimal('total_taxes_withheld', 13, 2)->default(0); 


            $table->decimal('holiday_pay_mwe', 13, 2)->default(0); 
            $table->decimal('overtime_pay_mwe', 13, 2)->default(0); 
            $table->decimal('night_shift_diff_mwe', 13, 2)->default(0); 
            $table->decimal('hazard_pay_mwe', 13, 2)->default(0); 
            $table->decimal('de_minimis_benefits', 13, 2)->default(0); 
            $table->decimal('salaries_other_nontax', 13, 2)->default(0); 


            $table->decimal('representation', 13, 2)->default(0); 
            $table->decimal('transportation', 13, 2)->default(0); 
            $table->decimal('cola', 13, 2)->default(0); 
            $table->decimal('fixed_housing', 13, 2)->default(0); 
            $table->string('others_taxable_a_label')->nullable();
            $table->decimal('others_taxable_a', 13, 2)->default(0); 
            $table->string('others_taxable_b_label')->nullable(); 
            $table->decimal('others_taxable_b', 13, 2)->default(0); 
            $table->decimal('commission', 13, 2)->default(0); 
            $table->decimal('profit_sharing', 13, 2)->default(0); 
            $table->decimal('fees', 13, 2)->default(0); 
            $table->decimal('hazard_pay_taxable', 13, 2)->default(0); 
            $table->string('others_51a_label')->nullable(); 
            $table->decimal('others_51a', 13, 2)->default(0); 
            $table->string('others_51b_label')->nullable(); 
            $table->decimal('others_51b', 13, 2)->default(0); 


            $table->string('previous_employer_name')->nullable();
            $table->string('previous_employer_tin')->nullable();
            $table->string('previous_employer_address')->nullable();
            $table->decimal('previous_employer_taxable_income', 13, 2)->default(0); 
            $table->decimal('previous_employer_tax_withheld', 13, 2)->default(0); 


            $table->decimal('five_percent_tax_credit', 13, 2)->default(0); 

            $table->string('status')->default('draft');
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->unsignedBigInteger('finalized_by')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['year', 'employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bir_2316');
    }
};
