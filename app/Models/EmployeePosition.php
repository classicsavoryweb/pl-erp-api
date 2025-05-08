<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class EmployeePosition extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ["employee_id", "position_id", "date_started", "remarks"];
    protected $table = "employee_position";

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Employee Position')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
