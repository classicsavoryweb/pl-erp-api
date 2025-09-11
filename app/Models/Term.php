<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Term extends Model
{
    use HasFactory, LogsActivity;

    protected $table = "terms";
    protected $fillable = ["description", "cutoff", "amount_from", "amount_to", "month"];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Terms')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
