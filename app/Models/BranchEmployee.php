<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BranchEmployee extends Pivot
{
    use HasFactory, LogsActivity;
    public $incrementing = true;
    protected $fillable = ["employee_id", "branch_id", "date_assigned", "remarks"];
    protected $table = "branch_employee";

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Branch Employee')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
