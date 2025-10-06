<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollList extends Model
{
    use HasFactory;

    protected $table = "payroll_list";

    protected $fillable = [
        "employee_id",
        "is_included",
        "is_statutory_only",
        "remarks",
    ];

    public function employee() {
        return $this->belongsTo(Employee::class, "employee_id", "id");
    }

    public function isIncludedInPayroll() {
        return $this->is_included === 1;
    }

    public function isStatutoryOnly() {
        return $this->is_statutory_only === 1;
    }
}
