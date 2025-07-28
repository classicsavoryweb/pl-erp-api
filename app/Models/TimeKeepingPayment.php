<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeKeepingPayment extends Model
{
    use HasFactory;

    protected $table = "time_keeping_payments";

    protected $fillable = [
        "timekeeping_id",
        "base_amount",
        "tardiness_deduction",
        "undertime_deduction",
        "total_amount",
    ];
}
