<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Employee;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Incentives extends Model
{
    use HasFactory, LogsActivity;

    protected $table = "incentives";
    protected $fillable = [
        "employee_id",
        "position_id",
        "branch_id",
        "incentive_amount",
        "max_amount",
        "remarks",
        "pay_date",
        "amount_rate",
        "additional_incentive",
        "adjustment_days",
        "adjustment_percent",
        "status",
        "first_cut_off_worked_days",
        "second_cut_off_worked_days",
        "additional_days",
        "month",
        "year",
        "undertime",
        "absent",
        "base_additional_incentive",
        "added_days"
    ];

    public function employee() {
        return $this->belongsTo(Employee::class);
    }

    public function position() {
        return $this->hasOne(Position::class, "id", "position_id");
    }

    public function payroll_list() {
        return $this->hasOne(PayrollList::class, "employee_id", "employee_id");
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Incentive History')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
