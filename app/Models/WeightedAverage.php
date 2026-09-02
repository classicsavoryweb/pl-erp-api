<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class WeightedAverage extends Model
{
    use HasFactory,LogsActivity;

    protected $table = 'weighted_averages';

    protected $fillable = [
        'weighted_id',
        'tag',
        'date_posted',
        'date_from',
        'date_to',
        'status',
        'overall_remarks',
        'prepared_by',
        'prepared_at',
        'reviewed_by',
        'reviewed_at',
        'posted_by',
        'posted_at',
        'cancelled_by',
        'cancelled_at',
        'cancel_reason',
    ];

    protected $casts = [
        'date_posted' => 'date:Y-m-d',
        'date_from' => 'date:Y-m-d',
        'date_to' => 'date:Y-m-d',
        'prepared_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'posted_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::created(function ($model) {
            $model->weighted_id = 'WA' . str_pad($model->id, 8, '0', STR_PAD_LEFT);
            $model->saveQuietly();
        });
    }

    public function items()
    {
        return $this->hasMany(WeightedAverageItems::class, 'weighted_id_number', 'weighted_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Uniform Weighted Average')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
