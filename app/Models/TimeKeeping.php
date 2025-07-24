<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeKeeping extends Model
{
    use HasFactory;

    protected $table="time_keeping";

    protected $fillable = [
        "employee_id",
        "biometric_number",
        "biotime_log_id",
        "schedule_time_id",
        "date",
        "worked_hours",
        "legal_holiday",
        "special_holiday",
        "regular_overtime",
        "night_diff_hours",
        "night_diff_overtime",
        "legal_holiday_hours",
        "legal_holiday_overtime",
        "special_holiday_hours",
        "special_holiday_overtime",
        "undertime",
        "tardiness",
        "remarks",
        "is_cancelled_off",
        "travel_time",
        "overtime_status",
        "branch_id",
        "approved_overtime",
        "final_worked_hours"
    ];

    public function employee() {
        return $this->belongsTo(Employee::class);
    }

    public function branch() {
        return $this->hasOne(Branch::class, "id", "branch_id");
    }
}
