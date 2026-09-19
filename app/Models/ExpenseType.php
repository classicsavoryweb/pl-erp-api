<?php

namespace App\Models;

use App\Models\Accounting\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ExpenseType extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'expense_types';

    protected $fillable = [
        'description',
        'debit_id',
        'credit_id',
        'status',
    ];

    public function debit()
    {
        return $this->hasOne(Account::class, 'acc_no', 'debit_id');
    }

    public function credit()
    {
        return $this->hasOne(Account::class, 'acc_no', 'credit_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Expense Type')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
