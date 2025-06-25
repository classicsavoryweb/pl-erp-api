<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Tardiness extends Model
{
    use HasFactory, LogsActivity;
    protected $table = "tardiness";
    protected $fillable = [
        "employee_id",
        "branch_id",
        "month",
        "year",
        "total_minutes",
        "tardiness_sanction_id",
        "offense_punishment",
        "reason",
        "status",
        "da_status",
        "nte_status",
        "da_date_sent",
        "nte_date_sent"
    ];

    public function employee() {
        return $this->belongsTo(Employee::class);
    }

    public function tardiness_sanction() {
        return $this->hasOne(TardinessSanction::class, "id", "tardiness_sanction_id");
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Employee Tardiness')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
