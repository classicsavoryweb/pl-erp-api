<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\RequestAndIssuanceItems;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class RequestAndIssuance extends Model
{
    use HasFactory,LogsActivity;

    protected $table ="request_and_issuances";

    protected $fillable = [
        'request_number',
        'employee_id',
        'employee_name',
        'issuance_type',
        'branch',
        'position',
        'date_hired',
        'date_requested',
        'date_reviewed',
        'date_posted',
        'date_cancelled',
        'total_amount',
        'status',
        'sent_email',
        'payroll_entry',
        'payroll_sent_date',
        'created_by',
        'reviewed_by',
        'posted_by',
        'cancelled_by',
        'cancel_reason',       

    ];

    protected static function booted()
    {
        static::created(function ($model) {

            $prefix = match ($model->issuance_type) {
                'Authority To Deduct' => 'ATD',
                'Free Uniform' => 'FR',
                default => throw new \InvalidArgumentException(
                    "Invalid issuance type: {$model->issuance_type}"
                ),
            };

            $model->request_number =
                $prefix . '-URN' . str_pad($model->id, 8, '0', STR_PAD_LEFT);

            $model->saveQuietly();
        });
    }

    public function items()
    {
        return $this->hasMany(RequestAndIssuanceItems::class, 'requested_number_id', 'request_number');
    }
    public function employee()
    {
        return $this->hasMany(Employee::class, 'employee_number', 'request_number');
    }
    
    public function branches()
    {
        return $this->hasOne(Branch::class, 'name' , 'branch');
    }
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('Uniform Request and Issuance')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
  