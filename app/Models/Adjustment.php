<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Adjustment extends Model
{
    use HasFactory;

    protected $table = "adjustments";
    protected $fillable = [
        "cutoff_period", 
        "employee_id", 
        "branch", 
        "reg_days",
        "cancelled_rd",
        "adjust_taxable",
        "remarks",
        "date",
        "status",
        "new_timein",
        "new_breakout",
        "new_breakin",
        "new_timeout",
        "amount_paid",
        "should_be_paid",
        "adjustment_type",
        "added_date",
        "added_by",
        "approved_date",
        "approved_by",
        "load_status",
        "load_date",
        "unapproved_date",
        "unapproved_by",
        "actual_schedule",
        "schedule_timein",
        "schedule_timeout",
        "cutoff_counter",
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, "employee_id", "biometric_number",);
    }
}
