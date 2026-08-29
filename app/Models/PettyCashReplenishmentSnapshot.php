<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PettyCashReplenishmentSnapshot extends Model
{
    use HasFactory;

    protected $table = 'petty_cash_replenishment_snapshots';

    protected $fillable = [
        'petty_cash_replenishment_id',
        'petty_cash_issuance_id',
        'pcv_number',
        'cshub_trans_no',
        'branch_id',
        'branch_name',
        'transaction_date',
        'requested_by_name',
        'amount',
        'unliquidated_amount',
        'overunder_amount',
        'status_at_snapshot',
    ];

    public function replenishment()
    {
        return $this->belongsTo(PettyCashReplenishment::class, 'petty_cash_replenishment_id');
    }

    public function issuance()
    {
        return $this->belongsTo(PettyCashIssuance::class, 'petty_cash_issuance_id');
    }
}
