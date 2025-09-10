<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    use HasFactory;

    protected $fillable = [
        "description",
        "rank",
        "status",
        "station_id",
        "schedule_type",
        "timekeeping_type"
    ];

    public function station() {
        return $this->hasOne(Station::class,"id","station_id");
    }

    public function salary() {
        return $this->hasOne(SalaryPosition::class, "position_id", "id");
    }

    public function employee() {
        return $this->belongsToMany(Employee::class);
    }

    public function acting_capacity() {
        return $this->belongsTo(ActingCapacity::class);
    }

    public function incentives() {
        return $this->belongsToMany(IncentivesBracket::class, 'position_incentives')
        ->using(PositionIncentives::class)
        ->withPivot("position_id", "incentives_bracket_id",)
        ->withTimestamps();
    }
}
