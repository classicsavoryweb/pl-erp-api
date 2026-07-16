<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PettyCashLiquidationAttachment extends Model
{
    use HasFactory;

    protected $table = 'petty_cash_liquidation_attachments';

    protected $fillable = [
        'petty_cash_liquidation_id',
        'file_path',
        'file_name',
    ];

    public function liquidation()
    {
        return $this->belongsTo(PettyCashLiquidation::class, 'petty_cash_liquidation_id');
    }
}
