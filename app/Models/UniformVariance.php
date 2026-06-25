<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UniformVariance extends Model
{
    protected $fillable = [
        'variance_no',
        'physical_count_id',
        'begin_date',
        'ending_date',
        'status',
        'remarks',
        'prepared_by',
        'reviewed_by',
        'reviewed_at',
        'posted_by',
        'posted_at',
        'cancelled_by',
        'cancelled_at',
        'cancelled_reason',
    ];

    protected $casts = [
        'begin_date' => 'date:Y-m-d',
        'ending_date' => 'date:Y-m-d',
        'reviewed_at' => 'datetime',
        'posted_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function physicalCount()
    {
        return $this->belongsTo(UniformPhysicalCount::class, 'physical_count_id');
    }

    public function items()
    {
        return $this->hasMany(UniformVarianceItem::class, 'variance_id');
    }
}
