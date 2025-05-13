<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class EmployeePosition extends Pivot
{
    use HasFactory, LogsActivity;
    public $incrementing = true;
    protected $fillable = ["employee_id", "position_id", "date_started", "oic_assignment", "date_appointed", "remarks"];
    protected $table = "employee_position";

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Employee Position')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
