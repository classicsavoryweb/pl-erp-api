<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class ScheduleTime extends Model
{
    use HasFactory, LogsActivity;

    protected $table = "schedule_time";
    protected $fillable = ["employee_id", "biometric_number", "date", "timetable", "check_in", "check_out", "branch_id"];
    protected static $recordEvents = ['updated'];

    public function employee() {
        return $this->belongsTo(Employee::class);
    }

    public function branch() {
        return $this->hasOne(Branch::class, "id", "branch_id");
    }

    public function timekeeping() {
        return $this->belongsTo(TimeKeeping::class, "id", "schedule_time_id");
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName("Schedule Time")
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

}
