<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryPosition extends Model
{
    use HasFactory;
    
    protected $fillable = ["position_id", "basic_salary", "rate_category", "status"];
    protected $table = "salary_positions";

    public function position() {
        return $this->belongsTo(Position::class);
    }
}
