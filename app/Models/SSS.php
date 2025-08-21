<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SSS extends Model
{
    use HasFactory, LogsActivity;

    protected $table = "sss";
    protected $fillable = ["salary_range_min", "salary_range_max", "salary_credit", "employer_ss", "employer_cc", "employee_ss"];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('SSS')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
