<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Dependents extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        "employee_id",
        "employee_number",
        "dependent_name",
        "birthday",
    ];

    protected $table= "dependents";

    public function employee() {
        return $this->belongsTo(Employee::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Employee Dependents')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
