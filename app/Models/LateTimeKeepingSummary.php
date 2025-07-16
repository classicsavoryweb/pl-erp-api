<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LateTimeKeepingSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        "timekeeping_summary_id",
        "date",
        "late_minutes"
    ];

    protected $table = "late_timekeeping_summary";

    public function timekeeping() {
        return $this->belongsTo(TimeKeepingSummary::class);
    }
}
