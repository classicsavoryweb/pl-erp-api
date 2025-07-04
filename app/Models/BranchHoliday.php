<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class BranchHoliday extends Pivot
{
    use HasFactory;

    public $incrementing = true;
    protected $fillable = ["branch_id", "holiday_id",];
    protected $table = "branch_holiday";

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function holiday()
    {
        return $this->belongsTo(Holiday::class);
    }
}
