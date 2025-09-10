<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncentivesBracket extends Model
{
    use HasFactory;

    protected $table = "incentives_bracket";

    protected $fillable = ["incentives_bracket_id", "bracket_name", "month", "amount"];

    protected $primaryKey = 'incentives_bracket_id';

    public $incrementing = false;

    public function positions()
    {
        return $this->belongsToMany(Position::class, 'position_incentives', 'incentives_bracket_id', 'position_id')
            ->using(PositionIncentives::class)
            ->withPivot('position_id', 'incentives_bracket_id')
            ->withTimestamps();
    }
}
