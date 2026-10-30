<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AdditionalDailyRate extends Model
{
    use HasFactory, LogsActivity;

    protected $table = "additional_daily_rates";

    protected $fillable = [
        "employee_id",
        "amount"
    ];

     public function getActivitylogOptions(): LogOptions
     {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Additional Daily Rate')
            ->dontSubmitEmptyLogs();
    }
}
