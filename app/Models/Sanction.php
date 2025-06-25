<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sanction extends Model
{
    use HasFactory;

    protected $table = "sanctions";
    protected $fillable = ["description", "suspension_days", "status"];

    public function employee_offense() {
        return $this->belongsTo(EmployeeOffense::class);
    }
}
