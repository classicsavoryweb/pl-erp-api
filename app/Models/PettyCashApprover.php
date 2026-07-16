<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PettyCashApprover extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'petty_cash_approvers';

    protected $fillable = [
        'description',
        'use_same_range',
        'min_amount',
        'max_amount',
        'status',
    ];

    protected $casts = [
        'use_same_range' => 'boolean',
    ];

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'petty_cash_approver_employee', 'approver_id', 'employee_id')
            ->withPivot('min_amount', 'max_amount')
            ->withTimestamps();
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'petty_cash_approver_branch', 'approver_id', 'branch_id')
            ->withTimestamps();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Petty Cash Approver')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
