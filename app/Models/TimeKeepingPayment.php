<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeKeepingPayment extends Model
{
    use HasFactory;

    protected $table = "time_keeping_payments";

    protected $fillable = [
        "timekeeping_id",
        "adjustment_timekeeping_id",
        "base_amount",
        "tardiness_deduction",
        "undertime_deduction",
        "regular_overtime",
        "night_differential",
        "night_differential_overtime",
        "legal_holiday",
        "special_holiday",
        "legal_holiday_overtime",
        "special_holiday_overtime",
        "total_amount",
        "type",
        "cancelled_off",
        "cancelled_off_overtime"
    ];
}
