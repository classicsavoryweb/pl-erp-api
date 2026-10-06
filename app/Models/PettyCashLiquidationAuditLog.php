<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PettyCashLiquidationAuditLog extends Model
{
    use HasFactory;

    protected $table = 'petty_cash_liquidation_audit_logs';

    protected $fillable = [
        'petty_cash_liquidation_id',
        'petty_cash_replenishment_id',
        'action',
        'reason',
        'old_values',
        'new_values',
        'performed_by_id',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function liquidation()
    {
        return $this->belongsTo(PettyCashLiquidation::class, 'petty_cash_liquidation_id');
    }

    public function replenishment()
    {
        return $this->belongsTo(PettyCashReplenishment::class, 'petty_cash_replenishment_id');
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by_id');
    }
}
