<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PettyCashReplenishment extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'petty_cash_replenishments';

    protected $fillable = [
        'replenishment_id',
        'date',
        'petty_cash_custodian_id',
        'branches_summary',
        'total_cash_issuance_amount',
        'total_overunder_amount',
        'total_replenishment_amount',
        'status',
        'void_reason',
        'created_by_id',
    ];

    public function custodian()
    {
        return $this->belongsTo(PettyCashCustodian::class, 'petty_cash_custodian_id');
    }

    public function snapshots()
    {
        return $this->hasMany(PettyCashReplenishmentSnapshot::class, 'petty_cash_replenishment_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Petty Cash Replenishment')
            ->logOnlyDirty();
    }
}
