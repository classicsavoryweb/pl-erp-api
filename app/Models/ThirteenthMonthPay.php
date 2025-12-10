<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThirteenthMonthPay extends Model
{
    use HasFactory;

    protected $table = "thirteenth_month_pay";
    protected $fillable = [
        "thirteenth_month_file_id",
        "employee_id",
        "december_2nd_cutoff",
        "january_1st_cutoff",
        "january_2nd_cutoff",
        "february_1st_cutoff",
        "february_2nd_cutoff",
        "march_1st_cutoff",
        "march_2nd_cutoff",
        "april_1st_cutoff",
        "april_2nd_cutoff",
        "may_1st_cutoff",
        "may_2nd_cutoff",
        "june_1st_cutoff",
        "june_2nd_cutoff",
        "july_1st_cutoff",
        "july_2nd_cutoff",
        "august_1st_cutoff",
        "august_2nd_cutoff",
        "september_1st_cutoff",
        "september_2nd_cutoff",
        "october_1st_cutoff",
        "october_2nd_cutoff",
        "november_1st_cutoff",
        "november_2nd_cutoff",
        "december_1st_cutoff",
        "year",
        "total",
        "net_claim"
    ];

    public function employee() {
        return $this->belongsTo(Employee::class, "employee_id", "id");
    }
}
