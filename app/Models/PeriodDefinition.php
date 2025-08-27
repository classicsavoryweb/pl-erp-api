<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PeriodDefinition extends Model
{
    use HasFactory, LogsActivity;

    protected $table = "period_definition";
    protected $fillable = ["month", "year", "status"];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Period Definition')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
