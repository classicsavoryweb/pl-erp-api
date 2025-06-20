<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EmployeeOffense extends Model
{
    use HasFactory, LogsActivity;
    protected $fillable = [
        'offense_id',
        'employee_id',
        'sanction_id',
        'transgression',
        'repetition',
        'summary',
        'date_committed',
        'offense_action',
        'date_served_ao',
        'suspension_dates',
        'date_served_da',
        'attachment',
        'attachment_file_name',
        'remarks',
        'tardiness_id',
    ];

    protected $table = "employee_offenses";

    public function offense(){
        return $this->hasOne(Offense::class,"id","offense_id");
    }

    public function sanction(){
        return $this->hasOne(Sanction::class,"id","sanction_id");
    }

    public function employee() {
        return $this->belongsTo(Employee::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Employee Offenses')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
