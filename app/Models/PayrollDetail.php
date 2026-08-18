<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PayrollDetail extends Model
{
    use HasFactory, LogsActivity;

    protected $table = "payroll_details";

    protected $fillable = [
        "employee_id",
        "payroll_id",
        "pay_date",
        "home_branch_id",
        "position_id",
        "is_hold",
        "is_on_maternity_leave",
        "is_mancom",
        "is_complete_documents",
        "schedule_type",
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
        "cancelled_off_overtime",

        "undertime_minutes",
        "tardiness_minutes",
        "remarks",
    ];

    public function employee() {
        return $this->belongsTo(Employee::class, "employee_id", "id");
    }
    public function payroll() {
        return $this->belongsTo(Payroll::class, "payroll_id", "id");
    }

    public function home_branch() {
        return $this->hasOne(Branch::class, "id", "home_branch_id");
    }
    public function position() {
        return $this->hasOne(Position::class, "id", "position_id");
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Payroll Detail')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
