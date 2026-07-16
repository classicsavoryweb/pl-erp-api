<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UniformPhysicalCountItem extends Model
{
    protected $fillable = [
        'physical_count_id',
        'uniform_item_id',
        'item_no',
        'description',
        'uom',
        'beginning_inventory',
        'purchasing_department',
        'made_to_order',
        'atd',
        'free_uniform',
        'total_purchases',
        'total_outgoing',
        'should_be_inventory',
        'physical_count',
        'standard_cost',
        'item_remarks',
        'total_amount'
    ];

    protected $casts = [
        'beginning_inventory' => 'decimal:2',
        'purchasing_department' => 'decimal:2',
        'made_to_order' => 'decimal:2',
        'atd' => 'decimal:2',
        'free_uniform' => 'decimal:2',
        'total_purchases' => 'decimal:2',
        'total_outgoing' => 'decimal:2',
        'should_be_inventory' => 'decimal:2',
        'physical_count' => 'decimal:2',
        'standard_cost' => 'decimal:2',
    ];

    public function physicalCount()
    {
        return $this->belongsTo(UniformPhysicalCount::class, 'physical_count_id');
    }

    public function item()
    {
        return $this->belongsTo(UniformItem::class, 'uniform_item_id');
    }
}
