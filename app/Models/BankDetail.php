<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BankDetail extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ["employee_id", "bank_number","bank_account_type_id" ,"remarks"];
    protected $table = "bank_details";

    public function bank_account_type() {
        return $this->hasOne(BankAccountType::class, "id", "bank_account_type_id");
    }

    public function employee() {
        return $this->belongsTo(Employee::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Bank Details')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
