<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PettyCashIssuance extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'petty_cash_issuances';

    protected $fillable = [
        'pcv_number',
        'date',
        'branch_id',
        'requested_by_employee_number',
        'received_by_employee_number',
        'approved_by_employee_number',
        'amount',
        'unliquidated_amount',
        'overunder_amount',
        'detailed_purpose',
        'status',
        'void_reason',
        'cshub_trans_no',
        'cshub_id',
        'source',
        'liquidated_at',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'liquidated_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(Employee::class, 'requested_by_employee_number', 'employee_number');
    }

    public function receivedBy()
    {
        return $this->belongsTo(Employee::class, 'received_by_employee_number', 'employee_number');
    }

    public function approvedBy()
    {
        return $this->belongsTo(Employee::class, 'approved_by_employee_number', 'employee_number');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Petty Cash Issuance')
            ->logOnlyDirty();
    }
}
