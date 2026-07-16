<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PettyCashCustodian extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'petty_cash_custodians';

    protected $fillable = ['user_id', 'total_cash_fund', 'remaining_cash_fund', 'status', 'created_by_id', 'updated_by_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'petty_cash_custodian_branch', 'custodian_id', 'branch_id')->withTimestamps();
    }

    public function departmentFundTransactions()
    {
        return $this->hasMany(PettyCashDepartmentFundTransaction::class, 'custodian_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Petty Cash Custodian')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
