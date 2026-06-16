<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\RequestAndIssuanceItems;
class RequestAndIssuance extends Model
{
    use HasFactory;

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
    public function items()
    {
        return $this->hasMany(RequestAndIssuanceItems::class, 'requested_number_id', 'request_number');
    }
    public function employee()
    {
        return $this->hasMany(Employee::class, 'employee_number', 'request_number');
    }
}
