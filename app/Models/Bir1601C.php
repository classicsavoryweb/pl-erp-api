<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bir1601C extends Model
{
    protected $table = "bir_1601c";

    public const STATUS_DRAFT = 'draft';
    public const STATUS_FINAL = 'final';

    protected $fillable = [
        "year",
        "month",
        "start_date",
        "end_date",
        "total_compensation",
        "statutory_minimum_wage",
        "holiday_pay_ot_ndf",
        "thirteenth_month_pay",
        "ee_contributions",
        "de_minimis_benefits",
        "other_non_taxable_compensation",
        "total_taxable_compensation",
        "taxable_compensation_not_subject_to_wtax",
        "net_taxable_compensation",
        "total_taxes_withheld",
        "add_less_adjustment",
        "tax_still_due",
        "total_remittance",
        "total_amount_due",
        "previous_month_1_period",
        "previous_month_1_date_paid",
        "previous_month_1_bank",
        "previous_month_1_ror_number",
        "previous_month_1_tax_paid",
        "previous_month_1_tax_due",
        "previous_month_2_period",
        "previous_month_2_date_paid",
        "previous_month_2_bank",
        "previous_month_2_ror_number",
        "previous_month_2_tax_paid",
        "previous_month_2_tax_due",
        "previous_month_3_period",
        "previous_month_3_date_paid",
        "previous_month_3_bank",
        "previous_month_3_ror_number",
        "previous_month_3_tax_paid",
        "previous_month_3_tax_due",
        "status",
        "generated_by",
        "finalized_by",
        "generated_at",
        "finalized_at",
        "remarks",
    ];

    protected $casts = [
        "start_date" => "date:Y-m-d",
        "end_date" => "date:Y-m-d",
        "previous_month_1_date_paid" => "date:Y-m-d",
        "previous_month_2_date_paid" => "date:Y-m-d",
        "previous_month_3_date_paid" => "date:Y-m-d",
        "generated_at" => "datetime",
        "finalized_at" => "datetime",
    ];

    public function generated_by_user()
    {
        return $this->hasOne(User::class, "id", "generated_by");
    }

    public function finalized_by_user()
    {
        return $this->hasOne(User::class, "id", "finalized_by");
    }
}
