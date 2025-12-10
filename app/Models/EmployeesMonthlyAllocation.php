<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeesMonthlyAllocation extends Model
{
    use HasFactory;

    protected $table = "employees_monthly_allocation";
    protected $fillable = [
        "thirteenth_month_file_id",
        "employee_id",
        "branch_short_name",
        "basic_pay_1st_cutoff",
        "basic_pay_2nd_cutoff",
        "total",
        "net_claim_13th_month",
        "net_claim_pay_leave",
        "net_claim_additional"
    ];

    public function employee() {
        return $this->belongsTo(Employee::class, "employee_id", "id");
    }
}
