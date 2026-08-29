<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UniformVarianceItem extends Model
{
    protected $fillable = [
        'variance_id',
        'uniform_item_id',
        'item_no',
        'description',
        'uom',
        'beginning_inventory',
        'purchasing_department',
        'made_to_order',
        'atd',
        'free_uniform',
        'should_be_inventory',
        'physical_count',
        'variance',
        'adjustment',
        'final_variance',
        'standard_cost',
        'total_variance_cost',
        'reason_of_adjustment',
    ];

    protected $casts = [
        'beginning_inventory' => 'decimal:2',
        'purchasing_department' => 'decimal:2',
        'made_to_order' => 'decimal:2',
        'atd' => 'decimal:2',
        'free_uniform' => 'decimal:2',
        'should_be_inventory' => 'decimal:2',
        'physical_count' => 'decimal:2',
        'variance' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'final_variance' => 'decimal:2',
        'standard_cost' => 'decimal:2',
        'total_variance_cost' => 'decimal:2',
    ];

    public function varianceHeader()
    {
        return $this->belongsTo(UniformVariance::class, 'variance_id');
    }

    public function item()
    {
        return $this->belongsTo(UniformItem::class, 'uniform_item_id');
    }
}
