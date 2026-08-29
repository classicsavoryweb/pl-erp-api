<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeightedAverageItems extends Model
{
    use HasFactory;

    protected $table = 'weighted_average_items';

    protected $fillable = [
        'weighted_id_number',
        'uniform_item_no',
        'description',
        'standard_uom',
        'current_price',
        'total_quantity',
        'standard_uom_two',
        'total_amount',
        'new_standard_price',
        'other_charges',
        'standard_difference',
        'final_standard_price',
        'reason',
    ];

    public function uniform()
    {
        return $this->hasOne(UniformItem::class, 'item_no', 'uniform_item_no');
    }

    public function weightedAverage()
    {
        return $this->belongsTo(WeightedAverage::class, 'weighted_id_number', 'weighted_id');
    }
}
