<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollDetail extends Model
{
    use HasFactory;

    protected $table = "payroll_details";

    protected $fillable = [
        "employee_id",
        "payroll_id",
        "pay_date",
        "home_branch_id",
        "position_id",
        "daily_rate",
        "hourly_rate",
        "minute_rate",
        
        //timekeeping summary details
        "regular_days",
        "special_leave",
        "cancelled_off",
        "special_holiday",
        "legal_holiday",
        "regular_overtime",
        "night_differential",
        "night_differential_overtime",
        "legal_holiday_overtime",
        "special_holiday_overtime",
        "undertime",
        "tardiness",
        "travel_time",
        "adjustment_amount",

        "undertime_minutes",
        "tardiness_minutes",
        "remarks",
    ];

    public function payroll() {
        return $this->belongsTo(Payroll::class, "payroll_id", "id");
    }
}
