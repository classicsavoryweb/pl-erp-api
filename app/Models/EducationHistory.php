<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EducationHistory extends Model
{
    use HasFactory;
    protected $fillable = [
        "employee_id",
        "employee_number",
        "college",
        "college_address",
        "course",
        "college_date_started",
        "college_date_graduated",
        "highschool",
        "highschool_address",
        "highschool_date_started",
        "highschool_date_graduated",
        "college_remarks",
        "highschool_remarks",
        "remarks",
    ];
    protected $table="education_history";

    public function employee() {
        return $this->belongsTo(Employee::class);
    }
}
