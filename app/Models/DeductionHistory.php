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
    protected $fillable = ["employee_id", "reference_doc", "deduction_id", "amount", "pay_date", "date_time"];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Deduction History')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
