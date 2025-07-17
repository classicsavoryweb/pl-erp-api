<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ScheduleTime extends Model
{
    use HasFactory;

    protected $table = "schedule_time";
    protected $fillable = ["employee_id", "biometric_number", "date", "timetable", "check_in", "check_out", "branch_id"];

    public function employee() {
        return $this->belongsTo(Employee::class);
    }

    public function branch() {
        return $this->hasOne(Branch::class, "id", "branch_id");
    }

}
