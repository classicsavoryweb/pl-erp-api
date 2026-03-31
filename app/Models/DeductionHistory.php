<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DeductionHistory extends Model
{
    use HasFactory, LogsActivity;

    protected $table = "deduction_history";
    protected $fillable = ["employee_id", "reference_doc", "deduction_id", "amount", "pay_date", "date_time", "old_balance", "new_balance"];

    public function employee() {
        return $this->belongsTo(Employee::class, "employee_id", "id");
    }

    public function deduction() {
        return $this->hasOne(Deduction::class,'id','deduction_id');
    }

    public function employeeDeductions()
    {
        return $this->hasMany(EmployeeDeduction::class, 'reference_doc', 'reference_doc');
    }

    public function actual_deduction()
    {
        return $this->hasOne(EmployeeDeduction::class, 'reference_doc', 'reference_doc');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Deduction History')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
