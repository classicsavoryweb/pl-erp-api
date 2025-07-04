<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $table = "holidays";
    protected $fillable = ["date", "holiday_type", "description", "required_days"];

    public function branches() {
        return $this->belongsToMany(Branch::class)
        ->using(BranchHoliday::class)
        ->withPivot("branch_id", "holiday_id",)
        ->withTimestamps();
    }

}
