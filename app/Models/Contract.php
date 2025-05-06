<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;


class Contract extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        "employee_id",
        "employee_number",
        "contract_number",
        "contract_start_date",
        "contract_end_date",
        "remarks",
        "status",
    ];

    protected $table= "contracts";

    public function employee() {
        return $this->belongsTo(Employee::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Contract')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
