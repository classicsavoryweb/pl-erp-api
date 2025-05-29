<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TardinessMinutes extends Model
{
    use HasFactory;


    protected $table = "tardiness_minutes";
    protected $fillable = ["employee_id", "late_date", "late_minutes", "month", "year", "reason"];
}
