<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Diagnosis extends Model
{
    use HasFactory;

    protected $fillable = [
        "description", "status"
    ];

    protected $table = "diagnosis";

    public function employee() {
        return $this->belongsToMany(Employee::class)
        ->using(EmployeeHealthSafety::class)
        ->withPivot(
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
        )
        ->withTimestamps();
    }
}
