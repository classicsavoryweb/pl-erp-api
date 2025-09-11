<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Employee;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class IncentivesPerEmployee extends Model
{
    use HasFactory, LogsActivity;

    protected $table = "incentives_per_employee";
    protected $fillable = ["employee_id", "amount", "max_amount"];

    public function employee() {
        return $this->belongsTo(Employee::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Incentives per Employee')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
