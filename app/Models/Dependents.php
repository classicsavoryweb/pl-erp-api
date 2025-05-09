<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dependents extends Model
{
    use HasFactory;

    protected $fillable = [
        "employee_id",
        "employee_number",
        "dependent_name",
        "birthday",
    ];

    protected $table= "dependents";

    public function employee() {
        return $this->belongsTo(Employee::class);
    }
}
