<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UniformPhysicalCount extends Model
{
    protected $fillable = [
        'physical_count_no',
        'begin_date',
        'ending_date',
        'counted_by',
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

     protected static function booted()
    {
        static::created(function ($model) {
            $model->physical_count_no = 'SAW-PC' . str_pad($model->id, 8, '0', STR_PAD_LEFT);
            $model->saveQuietly();
        });
    }

    public function items()
    {
        return $this->hasMany(UniformPhysicalCountItem::class, 'physical_count_id');
    }
}
