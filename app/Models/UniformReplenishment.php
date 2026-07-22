<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;

class UniformReplenishment extends Model
{
    protected $fillable = [
        'request_no', 'supplier_id', 'replenishment_type', 'requested_date',
        'status', 'total_amount', 'remarks', 'prepared_by', 'reviewed_by',
        'reviewed_at', 'posted_by', 'posted_at', 'cancelled_by',
        'cancelled_at', 'cancelled_reason', 'closed_by', 'closed_at',
        'closed_reason',
    ];

    protected $casts = [
        'requested_date' => 'date:Y-m-d',
        'total_amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'posted_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::created(function ($model) {
            $model->request_no = 'RPL' . str_pad($model->id, 8, '0', STR_PAD_LEFT);
            $model->saveQuietly();
        });
    }

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
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
         
            ->dontLogIfAttributesChangedOnly(["request_no"]);

    }
}
