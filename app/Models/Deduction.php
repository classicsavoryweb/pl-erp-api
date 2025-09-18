<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use App\Models\CSHubTransactionType;
use Spatie\Activitylog\Traits\LogsActivity;

class Deduction extends Model
{
    use HasFactory, LogsActivity;

    protected $table = "deductions";
    protected $fillable = ["description", "debit_id", "credit_id", "amortization_vendor_id", "amortization_debit_id", "amortization_credit_id", "status", "end_loan_vendor_id", "end_loan_debit_id", "end_loan_credit_id"];

    public function cs_hub_transaction_type() {
        return $this->belongsTo(CSHubTransactionType::class);
    }

    public function employee_deduction() {
        return $this->belongsTo(EmployeeDeduction::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Deductions')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
