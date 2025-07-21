<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Overtime extends Model
{
    use HasFactory;

    protected $table = "overtime";
    protected $fillable = ["date_filed", "biometrics_id", "overtime_date", "purpose", "total_hours", "status"];

    public function employee()
    {
        return $this->belongsTo(Employee::class, "biometrics_id", "biometric_number",);
    }
}
