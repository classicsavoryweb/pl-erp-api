<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncentivePosition extends Model
{
    use HasFactory;

    protected $table = "incentives_position";

    protected $fillable = [
        "position_id",
        "amount",
        "oic_additional_amount"
    ];

    public function position() {
        return $this->hasOne(Position::class, "id", "position_id");
    }
}
