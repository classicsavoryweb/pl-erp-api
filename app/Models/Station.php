<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Station extends Model
{
    use HasFactory;

    protected $fillable = [
        "name",
        "station_order",
        "status"
    ];

    public function position() {
        return $this->belongsTo(Position::class);
    }

    public function employee() {
        return $this->belongsTo(Employees::class);
    }
}
