<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Awobaz\Compoships\Compoships;

class EmployeeDeduction extends Model
{
    use HasFactory, LogsActivity, Compoships;

    protected $table = "employee_deductions";
    protected $fillable = [
        "employee_id",
        "deduction_id",
        "reference_doc",
        "months",
        "amount",
        "source_of_fund",
        "start_date",
        "end_date",
        "monthly_amortization",
        "balance",
        "remarks",
        "debit_id",
        "credit_id",
        "branch_id",
        "block_payment",
        "approved_by",
        "approved_date",
        "approved_status",
        "deduction_ref",
        "deduction_status",
        "created_by",
        "date_created",
        "end_loan_reason",
        "end_loan_status",
        "source",
        "transaction_number"
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $latest = static::orderBy('id', 'desc')->value('reference_doc');

            if ($latest) {
                $next = str_pad((int)$latest + 1, 9, '0', STR_PAD_LEFT);
            } else {
                $next = '000000001';
            }

            $model->reference_doc = $next;
        });
    }

    public function employee() {
        return $this->belongsTo(Employee::class);
    }

    public function deduction() {
        return $this->hasOne(Deduction::class,'id','deduction_id');
    }

    public function branch() {
        return $this->hasOne(Branch::class, "id", "branch_id");
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Employee Deductions')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
