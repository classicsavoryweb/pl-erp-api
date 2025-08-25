<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Philhealth extends Model
{
    use HasFactory, LogsActivity;

    protected $table = "philhealth";
    protected $fillable = ["salary_range_min", "salary_range_max", "salary_base", "employee_share", "employer_share", "total_monthly_contribution"];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Philhealth')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
