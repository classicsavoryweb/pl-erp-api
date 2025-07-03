<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleDetail extends Model
{
    use HasFactory;

    protected $table="schedule_details";
    protected $fillable = [
        "employee_id",
        "schedule_type_id",
        "incentive_leave",
        "flexible_schedule"
    ];

    public function employee() {
        return $this->belongsTo(Employee::class);
    }

    public function schedule_type() {
        return $this->hasOne(ScheduleType::class, "id", "schedule_type_id");
    }
}
