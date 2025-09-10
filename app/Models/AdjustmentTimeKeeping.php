<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdjustmentTimeKeeping extends Model
{
    use HasFactory;

    protected $fillable = [
        "employee_id",
        "biometric_number",
        "biotime_log_id",
        "schedule_time_id",
        "date",
        "worked_hours",
        "final_worked_hours",
        "legal_holiday",
        "special_holiday",
        "regular_overtime",
        "night_diff_hours",
        "night_diff_overtime",
        "legal_holiday_hours",
        "legal_holiday_overtime",
        "special_holiday_hours",
        "special_holiday_overtime",
        "tardiness",
        "undertime",
        "is_cancelled_off",
        "travel_time",
        "approved_overtime",
        "remarks",
        "cancelled_off_hours"
    ];

    
    protected $table = "adjustments_timekeeping";

    public function employee() {
        return $this->belongsTo(Employee::class, "employee_id", "id");
    }

    public function schedule() {
        return $this->hasOne(ScheduleTime::class, "id", "schedule_time_id");
    }

    public function overtime() {
        return $this->hasOne(Overtime::class, "adjustment_timekeeping_id", "id");
    }
}
