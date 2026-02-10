<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MancomSalary extends Model
{
    use HasFactory;

    protected $table = "mancom_salaries";

    protected $fillable = [
        "employee_number",
        "salary"
    ];

    public function employee() {
        return $this->belongsTo(Employee::class, "employee_number", "employee_number");
    }
}
