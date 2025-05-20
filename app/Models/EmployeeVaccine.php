<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class EmployeeVaccine extends Pivot
{
    use HasFactory;

    public $incrementing = true;
    protected $fillable = ["employee_id", "vaccine_id", "dose", "status", "date"];
    protected $table = "employee_vaccine";

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function vaccine()
    {
        return $this->belongsTo(Vaccine::class, "vaccine_id", "id");
    }
}
