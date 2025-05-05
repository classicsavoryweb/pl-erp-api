<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    use HasFactory;

    protected $fillable = [
        "employee_id",
        "employee_number",
        "contract_start",
        "contract_date",
        "remarks",
        "status",
    ];

    protected $table= "contracts";
}
