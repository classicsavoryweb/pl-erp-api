<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bir2316 extends Model
{
    protected $table = "bir_2316";

    public const STATUS_FINAL = 'final';

    public const SCHEDULE_1 = 'schedule_1';
    public const SCHEDULE_2 = 'schedule_2';

    protected $fillable = [
        "year",
        "employee_id",
        "schedule_type",
        "tin",
        "last_name",
        "first_name",
        "middle_name",
        "registered_address",
        "date_of_birth",
        "contact_number",
        "period_from",
        "period_to",
        "is_mwe",
        "smw_rate_per_day",
        "smw_rate_per_month",
        "rdo_code",
        "basic_salary_mwe",
        "thirteenth_month_pay",
        "sss_gsis_phic_hdmf_ee",
        "total_non_taxable",
        "basic_salary_taxable",
        "taxable_13th_month_excess",
        "overtime_pay_taxable",
        "total_taxable_regular",
        "gross_compensation",
        "less_non_taxable",
        "taxable_comp_present",
        "gross_taxable_comp",
        "tax_due",
        "taxes_withheld_present",
        "total_taxes_withheld",
        "holiday_pay_mwe",
        "overtime_pay_mwe",
        "night_shift_diff_mwe",
        "hazard_pay_mwe",
        "de_minimis_benefits",
        "salaries_other_nontax",
        "representation",
        "transportation",
        "cola",
        "fixed_housing",
        "others_taxable_a",
        "others_taxable_b",
        "commission",
        "profit_sharing",
        "fees",
        "hazard_pay_taxable",
        "others_51a",
        "others_51b",
        "previous_employer_name",
        "previous_employer_tin",
        "previous_employer_address",
        "previous_employer_taxable_income",
        "previous_employer_tax_withheld",
        "five_percent_tax_credit",
        "status",
        "generated_by",
        "generated_at",
        "remarks",
    ];

    protected $casts = [
        "period_from" => "date:Y-m-d",
        "period_to" => "date:Y-m-d",
        "date_of_birth" => "date:Y-m-d",
        "is_mwe" => "boolean",
        "generated_at" => "datetime",
    ];

    public function full_name(): string
    {
        return trim("{$this->last_name}, {$this->first_name} " . ($this->middle_name ?? ''));
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, "employee_id");
    }

    public function generated_by_user()
    {
        return $this->hasOne(User::class, "id", "generated_by");
    }
}
