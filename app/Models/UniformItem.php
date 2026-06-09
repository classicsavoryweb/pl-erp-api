<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class UniformItem extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'item_no',
        'description',
        'uniform_size_id',
        'uniform_gender_id',
        'standard_unit_price',
        'stock_on_hand',
        'status',
        'standard_qty',
        'standard_unit',
        'min_order',
        'order_limit',
        'crit_level',
        'minimum_rpl',
        'free_uniform',
        'made_to_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'free_uniform' => 'boolean',
        'made_to_order' => 'boolean',
    ];

    public function size()
    {
        return $this->belongsTo(Sizes::class, 'uniform_size_id');
    }

    public function gender()
    {
        return $this->belongsTo(UniformGender::class, 'uniform_gender_id');
    }

    public function suppliers()
    {
        return $this->hasMany(UniformItemSupplier::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Uniform Item Masterlist')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
