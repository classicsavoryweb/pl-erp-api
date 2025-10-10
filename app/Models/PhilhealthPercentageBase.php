<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PhilhealthPercentageBase extends Model
{
    use HasFactory, LogsActivity;

    protected $table = "philhealth_percentage_base";
    protected $fillable = ["philhealth_formula", "percentage", "philhealth_cap", "philhealth_max"];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Philhealth Percentage Base')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
