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
        "salary_amount",
        "is_included",
        "is_statutory_only",
        "is_mancom",
        "is_on_maternity_leave",
        "is_bank",
        "is_hold",
        "remarks",
        "holded_at",
        "holded_cutoffs"
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

    public function isMancom() {
        return $this->is_mancom === 1;
    }

    public function isOnMaternityLeave() {
        return $this->is_on_maternity_leave === 1;
    }
}
