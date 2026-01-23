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
        "final_worked_hours",
        "cancelled_off_hours",
        "paid_holiday"
    ];

    public function employee() {
        return $this->belongsTo(Employee::class, "employee_id", "id");
    }

    public function branch() {
        return $this->hasOne(Branch::class, "id", "branch_id");
    }

    public function biotime_log() {
        return $this->hasOne(BiotimeLog::class, "id", "biotime_log_id");
    }

    public function schedule() {
        return $this->hasOne(ScheduleTime::class, "id", "schedule_time_id");
    }

    public function payment() {
        return $this->hasOne(TimeKeepingPayment::class, "timekeeping_id", "id");
    }

    public function overtime() {
        return $this->hasOne(Overtime::class, "id", "timekeeping_id");
    }
}
