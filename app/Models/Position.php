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
        "station_id"
    ];

    public function station() {
        return $this->hasOne(Station::class,"id","station_id");
    }
}
