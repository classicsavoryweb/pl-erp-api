<?php

namespace App\Models;

use App\Models\Accounting\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;
    protected $table = "branches";
    protected $fillable = [
        "name",
        "branch_number",
        "status",
        "travel_time",
        "device_name",
        "short_name",
        "type",
        "address",
        "location",
        "date_open",
        "company",
        "branch_email",
        "rate_code",
        'branch_head_employee_number',
        "group_head_employee_number",
        'branch_head',
        "group_head",
        'kitchen_oic_employee_number',
        'kitchen_oic',
        'mancom_employee_number',
        'mancom',
        'expense_debit',
        'expense_credit',
        'advance_debit',
        'advance_credit',
        'ho_13th_debit',
        'ho_13th_debit_1',
        'ho_13th_credit',
        'branch_ho_13th_debit',
        'branch_ho_13th_debit_1',
        'branch_ho_13th_credit',
        'branch_ho_stat_debit',
        'branch_ho_stat_credit',
    ];

    public function employee() {
        return $this->belongsToMany(Employee::class);
    }

    public function acting_capacity() {
        return $this->belongsTo(ActingCapacity::class);
    }

    public function kitchen_oic() {
        return $this->hasOne(Employee::class, "employee_number", "kitchen_oic_employee_number");
    }

    public function branch_oic() {
        return $this->hasOne(Employee::class, "employee_number", "branch_head_employee_number");
    }

    public function group_head() {
        return $this->hasOne(Employee::class, "employee_number", "group_head_employee_number");
    }
    public function expense_debit() {
        return $this->belongsTo(Account::class, 'expense_debit', 'acc_no');
    }

    public function expense_credit() {
        return $this->belongsTo(Account::class, 'expense_credit', 'acc_no');
    }

    public function advance_debit() {
        return $this->belongsTo(Account::class, 'advance_debit', 'acc_no');
    }

    public function advance_credit() {
        return $this->belongsTo(Account::class, 'advance_credit', 'acc_no');
    }

    public function schedule_time() {
        return $this->belongsTo(ScheduleTime::class);
    }

    public function time_keeping() {
        return $this->belongsTo(TimeKeeping::class);
    }

    public function biotime_punch() {
        return $this->belongsTo(BiotimePunch::class);
    }

    public function time_keeping_summary() {
        return $this->belongsTo(TimeKeepingSummary::class);
    }

    public function employee_deduction() {
        return $this->belongsTo(EmployeeDeduction::class);
    }

    public function holidays() {
        return $this->belongsToMany(Holiday::class)
        ->using(BranchHoliday::class)
        ->withPivot("branch_id", "holiday_id",)
        ->withTimestamps();
    }
}
