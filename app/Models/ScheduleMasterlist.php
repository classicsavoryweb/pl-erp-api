<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleMasterlist extends Model
{
    use HasFactory;

    protected $table = "schedule_masterlist";
    protected $fillable = ["start_time", "end_time", "selection", "classification", "time_break", "total_hours", "days", "required"];

}
