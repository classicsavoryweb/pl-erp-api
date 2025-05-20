<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vaccine extends Model
{
    use HasFactory;

    protected $fillable = [
        "description",
        "status"
    ];

    protected $table = "vaccines";

    public function employee() {
        return $this->belongsToMany(Employee::class)
        ->using(EmployeeVaccine::class)
        ->withPivot("dose", "status", "date")
        ->withTimestamps();
    }
}
