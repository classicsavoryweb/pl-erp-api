<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UniformReceivingItem extends Model
{
    protected $fillable = [
        'receiving_id', 'replenishment_item_id', 'uniform_item_id',
        'quantity', 'unit_price', 'remarks',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
    ];

    protected $appends = ['total_amount'];

    public function item()
    {
        return $this->belongsTo(UniformItem::class, 'uniform_item_id');
    }

      public function receiving()
    {
        return $this->belongsTo(UniformReceiving::class, 'receiving_id');
    }

    

    public function getTotalAmountAttribute(): float
    {
        return (float) $this->quantity * (float) $this->unit_price;
    }
}
