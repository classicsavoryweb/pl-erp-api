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
        "highchool",
        "highchool_address",
        "highchool_date_started",
        "highchool_date_graduated",
    ];
    protected $table="education_history";

    public function employee() {
        return $this->belongsTo(Employee::class);
    }
}
