<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActingCapacityReason extends Model
{
    use HasFactory;

    protected $table = "acting_capacity_reason";

    protected $fillable = [
        "reason",
        "status"
    ];
}
