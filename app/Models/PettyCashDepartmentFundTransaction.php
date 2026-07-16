<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PettyCashDepartmentFundTransaction extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'petty_cash_department_fund_transactions';

    protected $fillable = [
        'custodian_id',
        'transaction_type_id',
        'report_id',
        'cash_fund',
        'reason',
        'created_by_id',
    ];

    public function custodian()
    {
        return $this->belongsTo(PettyCashCustodian::class, 'custodian_id');
    }

    public function transactionType()
    {
        return $this->belongsTo(PettyCashTransactionType::class, 'transaction_type_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Petty Cash Department Fund')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
