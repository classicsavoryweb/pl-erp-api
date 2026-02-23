<?php

namespace App\Models;

use App\Models\Accounting\Account;
use App\Models\Accounting\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use App\Models\CSHubTransactionType;
use Spatie\Activitylog\Traits\LogsActivity;

class Deduction extends Model
{
    use HasFactory, LogsActivity;

    protected $table = "deductions";
    protected $fillable = [
        "description",
        "category",
        "debit_id",
        "credit_id",
        "amortization_vendor_id",
        "amortization_debit_id",
        "amortization_credit_id",
        "status",
        "end_loan_vendor_id",
        "end_loan_debit_id",
        "end_loan_credit_id",
        "payslip_description",
        "is_block_payment"
    ];

    public function cs_hub_transaction_type() {
        return $this->belongsTo(CSHubTransactionType::class);
    }

    public function employee_deduction() {
        return $this->belongsTo(EmployeeDeduction::class);
    }

    public function amortization_debit() {
        return $this->belongsTo(Account::class, 'amortization_debit_id', 'acc_no');
    }

    public function amortization_credit() {
        return $this->belongsTo(Account::class, 'amortization_credit_id', 'acc_no');
    }

    public function amortization_vendor() {
        return $this->belongsTo(Vendor::class, 'amortization_vendor_id', 'ven_no');
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
