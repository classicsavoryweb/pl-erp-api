<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tardiness extends Model
{
    use HasFactory;
    protected $table = "tardiness";
    protected $fillable = [
        "employee_id",
        "branch_id",
        "month",
        "year",
        "total_minutes",
        "tardiness_sanction_id",
        "offense_punishment",
        "reason",
        "status",
        "da_status",
        "nte_status",
    ];

    public function employee() {
        return $this->belongsTo(Employee::class);
    }

    public function tardiness_sanction() {
        return $this->hasOne(TardinessSanction::class, "id", "tardiness_sanction_id");
    }
}
