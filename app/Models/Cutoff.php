<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cutoff extends Model
{
    use HasFactory;

    protected $table = "cut_off";
    protected $fillable = [
        "cut_off",
        "is_timekeeping_locked",
        "is_cutoff_initialized",
        "is_cutoff_locked",
        "pay_date",
        "lock_unlocked_date",
        "initialized_date",
        "lock_unlocked_by",
        "initialized_by",
        "cutoff_locked_by",
        "cutoff_locked_date",
        "status",
    ];
}
