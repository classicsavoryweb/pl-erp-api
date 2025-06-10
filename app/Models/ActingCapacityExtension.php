<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActingCapacityExtension extends Model
{
    use HasFactory;

    protected $table = "acting_capacity_extension";

    protected $fillable = [
        "acting_capacity_id",
        "end_date",
        "reason",
        "remarks",
    ];

    public function acting_capacity() {
        return $this->belongsTo(ActingCapacity::class);
    }
}
