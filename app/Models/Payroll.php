<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Payroll extends Model
{
    use HasFactory, LogsActivity;

    protected $table = "payroll";

    protected $fillable = [
        "employee_id",
        "timekeeping_summary_id",
        "timekeeping_branch_id",
        "pay_date",
        "cut_off",
        "start_date",
        "end_date",
        "daily_rate",
        "hourly_rate",
        "pay_type",
        "tax_status",
        "salary",
        "tardiness_amount",
        "undertime_amount",
        "overtime_amount",
        "legal_holiday_amount",
        "legal_holiday_overtime_amount",
        "special_holiday_amount",
        "special_holiday_overtime_amount",
        "night_differential_amount",
        "night_differential_overtime_amount",
        "taxable_adjustment",
        "non_taxable_adjustment",
        "cancelled_off",
        "gross_pay",
        "employee_sss",
        "employer_sss",
        "employee_pagibig",
        "employer_pagibig",
        "employee_philhealth",
        "employer_philhealth",
        "withholding_tax",
        "deductions",
        "total_deductions",
        "manual_deductions",
        "net_pay",
        "status",
        "taxable_income",
        "contribution_total",
        "additional_working_hours_total",
        "working_hours_deduction_total",
        "remarks",
        "is_maternity",
        "is_hold",
        "position_id",
        "special_leave",
        "is_manual_statutory_entry",
        "cancelled_off_overtime_amount"
    ];

    protected $casts = [
        'deductions' => 'array',
    ];

    public function employee () {
        return $this->belongsTo(Employee::class);
    }

    public function payroll_list() {
        return $this->hasOne(PayrollList::class, "employee_id", "employee_id");
    }

    public function timekeeping_summary () {
        return $this->hasOne(TimeKeepingSummary::class, "id", "timekeeping_summary_id");
    }

    public function position() {
        return $this->hasOne(Position::class, "id", "position_id");
    }

    public function lates() {
        return $this->hasMany(LateTimeKeepingSummary::class, "timekeeping_summary_id", "timekeeping_summary_id");
    }

    public function timekeeping_branch() {
        return $this->hasOne(Branch::class, "id", "timekeeping_branch_id");
    }

    public function details() {
        return $this->hasOne(PayrollDetail::class, "payroll_id", "id");
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Payroll')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
