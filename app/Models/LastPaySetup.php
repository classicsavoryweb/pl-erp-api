<?php

namespace App\Models;

use App\Models\Accounting\Account;
use App\Models\Accounting\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LastPaySetup extends Model
{
    use HasFactory, LogsActivity;

    protected $table = "last_pay_setup";
    protected $fillable = ["debit_id", "credit_id", "setup_type", "vendor_id"];

    public function vendor() {
        return $this->hasOne(Vendor::class,'ven_no','vendor_id');
    }

    public function debit() {
        return $this->hasOne(Account::class,'acc_no','debit_id');
    }

    public function credit() {
        return $this->hasOne(Account::class,'acc_no','credit_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Last Pay Setup')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
