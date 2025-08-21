<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Tax extends Model
{
    use HasFactory, LogsActivity;

    protected $table = "tax";
    protected $fillable = ["type", "status", "range_min", "range_max", "salary_base", "percentage", "exemption"];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Tax')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
