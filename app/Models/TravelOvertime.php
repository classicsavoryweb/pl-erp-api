<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TravelOvertime extends Model
{
    use HasFactory;

    protected $table = "travel_overtimes";

    protected $fillable = [
        "employee_id",
        "date",
        "travel_time",
        "remarks"
    ];

    public function employee() {
        return $this->belongsTo(Employee::class, "employee_id", "id");
    }
}
