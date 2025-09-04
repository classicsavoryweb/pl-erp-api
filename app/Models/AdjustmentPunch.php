<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdjustmentPunch extends Model
{
    use HasFactory;

    protected $table = "adjustments_punches";

    protected $fillable = [
        "adjustment_id",
        "date",
        "punch_time",
        "branch_id",
    ];

    public function branch() {
        return $this->hasOne(Branch::class, "id", "branch_id");
    }
}
