<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;
class PositionIncentives extends Pivot
{
    use HasFactory;

    protected $fillable = ["position_id", "incentives_bracket_id"];
    protected $table = "position_incentives";

    
    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function incentive()
    {
        return $this->belongsTo(IncentivesBracket::class);
    }
}
