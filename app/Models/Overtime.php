<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Overtime extends Model
{
    use HasFactory;

    protected $table = "overtime";
    protected $fillable = [
        "date_filed",
        "employee_id",
        "biometric_number",
        "overtime_date",
        "purpose",
        "total_hours",
        "status",
        "hours_paid",
        "timekeeping_id",
        "adjustment_timekeeping_id",
        "cutoff_paid",
        "payment_type",
        "cshub_overtime_id",
        "approve_date"
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, "biometric_number", "biometric_number",);
    }

    public function timekeeping()
    {
        return $this->belongsTo(TimeKeeping::class, "timekeeping_id" ,"id");
    }

    public function adjustment_timekeeping()
    {
        return $this->belongsTo(AdjustmentTimeKeeping::class, "adjustment_timekeeping_id", "id");
    }
}
