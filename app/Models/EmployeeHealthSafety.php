<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeHealthSafety extends Model
{
    use HasFactory;

    protected $fillable = [
        "employee_id",
        "diagnosis_id",
        "consultation_date",
        "last_duty_date",
        "hso",
        "chief_complaint",
        "attending_physician",
        "physician_name",
        "swab_test",
        "swab_test_date",
        "swab_test_type",
        "swab_test_result",
        "return_to_work_date",
        "quarantine_period_from",
        "quarantine_period_to",
    ];

    protected $table = "employee_health_safety";

    public function employee() {
        return $this->belongsTo(Employee::class);
    }

    public function diagnosis() {
        return $this->belongsTo(Diagnosis::class, "diagnosis_id", "id");
    }
}
