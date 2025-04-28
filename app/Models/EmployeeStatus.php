<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeStatus extends Model
{
    use HasFactory;

    protected $table =  "employee_status";

    protected $fillable = [
        "type",
        "description"
    ];
}

