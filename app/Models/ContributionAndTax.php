<?php

namespace App\Models;

use App\Models\Accounting\Account;
use App\Models\Accounting\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ContributionAndTax extends Model
{
    use HasFactory, LogsActivity;

    protected $table = "contributions_and_tax";
    protected $fillable = ["vendor_id", "debit_id", "credit_id", "vendor_name"];

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
            ->useLogName('Contributions and Tax')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
