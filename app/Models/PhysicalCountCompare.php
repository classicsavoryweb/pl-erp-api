<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhysicalCountCompare extends Model
{
    protected $table = 'physical_count_compare_table';

    protected $fillable = [
        'uniform_item_id',
        'physical_count_id',
        'item_no',
        'beginning_date',
        'stock_on_hand',
    ];

    protected $casts = [
        'beginning_date' => 'date:Y-m-d',
        'stock_on_hand' => 'decimal:2',
    ];

    public function item()
    {
        return $this->belongsTo(UniformItem::class, 'uniform_item_id');
    }
}
