<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BiotimePunch extends Model
{
    use HasFactory;

    protected $fillable = [
        "biotime_log_id",
        "date",
        "punch_time",
        "remarks",
        "branch_id"
    ];

    protected $table="biotime_punches";

    public function biotime() {
        return $this->belongsTo(BiotimeLog::class);
    }

    public function branch() {
        return $this->hasOne(Branch::class, "id", "branch_id");
    }
}
