<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class UniformReceiving extends Model
{
    use HasFactory, LogsActivity;
    protected $fillable = [
        'receiving_no', 'replenishment_id', 'supplier_id', 'date_received',
        'crf_no', 'document_no', 'checker_name', 'transmitted_by', 'status',
        'total_amount', 'remarks', 'prepared_by', 'reviewed_by', 'reviewed_at',
        'posted_by', 'posted_at', 'cancelled_by', 'cancelled_at', 'cancelled_reason',
    ];

    protected $casts = [
        'date_received' => 'date:Y-m-d',
        'total_amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'posted_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

     protected static function booted()
     {
        static::created(function ($model) {
            $model->receiving_no = 'RCV' . str_pad($model->id, 8, '0', STR_PAD_LEFT);
            $model->saveQuietly();
        });
    }

    public function replenishment()
    {
        return $this->belongsTo(UniformReplenishment::class, 'replenishment_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(UniformReceivingItem::class, 'receiving_id');
    }
    
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Uniform Receiving Stocks')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
