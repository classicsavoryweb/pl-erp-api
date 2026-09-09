<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduledTimekeepingGeneration extends Model
{
    use HasFactory;

    protected $table = "scheduled_timekeeping_generation";
    protected $fillable = [
        "cut_off_id",
        "generated_by",
        "schedule",
        "status"
    ];

    public function user() {
        return $this->hasOne(User::class, "id", "generated_by");
    }

    public function cut_off() {
        return $this->hasOne(Cutoff::class, "id", "cut_off_id");
    }
}
