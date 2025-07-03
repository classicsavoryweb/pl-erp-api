<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleType extends Model
{
    use HasFactory;

    protected $table = "schedule_type";
    protected $fillable = [
        "name",
        "status"
    ];

    public function schedule() {
        return $this->belongsTo(ScheduleDetail::class);
    }
}
