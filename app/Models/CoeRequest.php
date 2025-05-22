<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'date_requested',
        "include_salary",
        "coe_format",
        "salary",
        "coe_reason",
        "status",
    ];

    protected $table="coe_requests";

    public function employee(){
        return $this->belongsTo(Employee::class);
    }
}
