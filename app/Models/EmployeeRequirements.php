<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeRequirements extends Model
{
    use HasFactory;

    protected $fillable = [
        "employee_id",
        "employee_number",
        "birth_certificate",
        "marriage_certificate",
        "birth_certificate_dependents",
        "sss",
        "check_1902",
        "check_1905",
        "philhealth",
        "pagibig",
        "tin",
        "picture2x2",
        "check_diploma",
        "tor",
        "cot",
        "coe",
        "nbi",
        "drug_test",
        "clinical_microscopy",
        "chest_xray",
        "occupational_permit",
    ];
    protected $table = "employee_requirements";
}
