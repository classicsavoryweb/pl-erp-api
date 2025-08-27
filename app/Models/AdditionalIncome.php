<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AdditionalIncome extends Model
{
    use HasFactory, LogsActivity;

    protected $table = "additional_income";
    protected $fillable = ["description", "taxable", "status", "head_office_debit_id", "head_office_credit_id", "store_debit_id", "store_credit_id",];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Additional Income')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
