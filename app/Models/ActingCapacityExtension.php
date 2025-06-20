<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ActingCapacityExtension extends Model
{
    use HasFactory, LogsActivity;

    protected $table = "acting_capacity_extension";

    protected $fillable = [
        "acting_capacity_id",
        "end_date",
        "reason",
        "remarks",
    ];

    public function acting_capacity() {
        return $this->belongsTo(ActingCapacity::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Employee Acting Position Extension')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
