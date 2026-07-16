<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PettyCashLiquidation extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'petty_cash_liquidations';

    protected $fillable = [
        'liquidation_id',
        'petty_cash_issuance_id',
        'date',
        'liquidation_type',
        'requested_by_employee_number',
        'reference_document_number',
        'reference_document_date',
        'description',
        'amount',
        'expense_type_id',
        'created_by_id',
        'updated_by_id',
    ];

    public function issuance()
    {
        return $this->belongsTo(PettyCashIssuance::class, 'petty_cash_issuance_id');
    }

    public function expenseType()
    {
        return $this->belongsTo(ExpenseType::class, 'expense_type_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(Employee::class, 'requested_by_employee_number', 'employee_number');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function attachments()
    {
        return $this->hasMany(PettyCashLiquidationAttachment::class, 'petty_cash_liquidation_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Petty Cash Liquidation')
            ->logOnlyDirty();
    }
}
