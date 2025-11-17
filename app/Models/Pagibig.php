<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Pagibig extends Model
{
    use HasFactory, LogsActivity;

    protected $table = "pagibig";
    protected $fillable = [
        "min_amount",
        "max_amount",
        "percentage",
        "max_deduction",
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Pagibig')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
