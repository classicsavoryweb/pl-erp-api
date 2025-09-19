<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeKeepingSummary extends Model
{
    use HasFactory;

    protected $table = "timekeeping_summary";
    protected $fillable = [
        "cut_off",
        "employee_id",
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
        "travel_time",
        "adjustment_amount",
        "remarks",
        "tardiness",
        "branch_id"
    ];

    public function lates() {
        return $this->hasMany(LateTimeKeepingSummary::class, "timekeeping_summary_id", "id");
    }

    public function employee() {
        return $this->belongsTo(Employee::class);
    }

    public function branch() {
        return $this->hasOne(Branch::class, "id", "branch_id");
    }
}
