<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PettyCashTransactionType extends Model
{
    use HasFactory;

    protected $table = 'petty_cash_transaction_types';

    protected $fillable = [
        'name',
        'cash_fund_required',
        'report_id_required',
        'effect_direction',
        'effect_target',
        'status',
    ];

    protected $casts = [
        'cash_fund_required' => 'boolean',
        'report_id_required' => 'boolean',
    ];

    public function transactions()
    {
        return $this->hasMany(PettyCashDepartmentFundTransaction::class, 'transaction_type_id');
    }
}
