<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UniformReplenishment extends Model
{
    protected $fillable = [
        'request_no', 'supplier_id', 'replenishment_type', 'requested_date',
        'status', 'total_amount', 'remarks', 'prepared_by', 'reviewed_by',
        'reviewed_at', 'posted_by', 'posted_at', 'cancelled_by',
        'cancelled_at', 'cancelled_reason',
    ];

    protected $casts = [
        'requested_date' => 'date:Y-m-d',
        'total_amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'posted_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(UniformReplenishmentItem::class, 'replenishment_id');
    }

    public function receivings()
    {
        return $this->hasMany(UniformReceiving::class, 'replenishment_id');
    }
}
