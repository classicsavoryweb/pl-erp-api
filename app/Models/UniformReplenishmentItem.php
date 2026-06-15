<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UniformReplenishmentItem extends Model
{
    protected $fillable = [
        'replenishment_id', 'uniform_item_id', 'requested_qty',
        'approved_qty', 'received_qty', 'unit_price', 'remarks',
    ];

    protected $casts = [
        'requested_qty' => 'decimal:2',
        'approved_qty' => 'decimal:2',
        'received_qty' => 'decimal:2',
        'unit_price' => 'decimal:2',
    ];

    protected $appends = ['remaining_qty', 'total_amount'];

    public function item()
    {
        return $this->belongsTo(UniformItem::class, 'uniform_item_id');
    }

    public function getRemainingQtyAttribute(): float
    {
        return max(0, (float) $this->approved_qty - (float) $this->received_qty);
    }

    public function getTotalAmountAttribute(): float
    {
        return (float) $this->approved_qty * (float) $this->unit_price;
    }
}
